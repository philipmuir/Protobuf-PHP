<?php

namespace DrSlump\Protobuf\Compiler;

use DrSlump\Protobuf;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ProcessProtoFileCommand extends Command
{

    protected $pluginExecutable;

    public function __construct($name = null, $pluginExecutable = '')
    {
        parent::__construct($name);

        $this->pluginExecutable = $pluginExecutable;
    }

    protected function configure()
    {
        $this
            ->setName('proto:generate')
            ->setDescription('Process .proto files and generate PHP code.')
            ->addOption(
                'out',
                'o',
                InputOption::VALUE_REQUIRED,
                'destination directory for generated files',
                './'
            )
            ->addOption(
                'include',
                'i',
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'define an include path (can be repeated)'
            )
            ->addOption(
                'json',
                'j',
                InputOption::VALUE_OPTIONAL,
                'turn on ProtoJson Javascript file generation'
            )
            ->addOption(
                'protoc',
                'pc',
                InputOption::VALUE_OPTIONAL,
                'protoc compiler executable path',
                'protoc'
            )
            ->addOption(
                'skipImported',
                'si',
                InputOption::VALUE_OPTIONAL,
                'do not generate imported proto files',
                false
            )
            ->addOption(
                'comments',
                'c',
                InputOption::VALUE_OPTIONAL,
                'port .proto comments to generated code',
                false
            )
            ->addOption(
                'insertions',
                'ins',
                InputOption::VALUE_OPTIONAL,
                'generate @@protoc insertion points'
            )
            ->addOption(
                'define',
                'D',
                InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY,
                'define a generator option (ie: -Dmultifile -Dsuffix=pb.php)'
            )
            ->addArgument(
                'protos',
                InputArgument::IS_ARRAY,
                'proto files'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {

        // PHP doesn't implement non-blocking stdin on Windows
        // https://bugs.php.net/bug.php?id=34972
        $isWin = 'WIN' === strtoupper(substr(PHP_OS, 0, 3));
        if ($isWin) {
            $this->pluginExecutable .= '.bat';
        }

        $protocBin = $input->getOption('protoc');

        // Check if protoc is available
        $execOutput = '';
        exec("$protocBin --version", $execOutput, $return);

        if (0 !== $return && 1 !== $return) {
            fputs(STDERR, "ERROR: Unable to find the protoc command.". PHP_EOL);
            fputs(STDERR, "       Please make sure it's installed and available in the path." . PHP_EOL);
            exit(1);
        }

        if (!preg_match('/[0-9\.]+/', $execOutput[0], $m)) {
            fputs(STDERR, "ERROR: Unable to get protoc command version.". PHP_EOL);
            fputs(STDERR, "       Please make sure it's installed and available in the path." . PHP_EOL);
            exit(1);
        }

        if (version_compare($m[0], '2.3.0') < 0) {
            fputs(STDERR, "ERROR: The protoc command in your system is too old." . PHP_EOL);
            fputs(STDERR, "       Minimum version required is 2.3.0 but found {$m[0]}." . PHP_EOL);
            exit(1);
        }


        $cmd[] = $protocBin;
        $cmd[] = '--plugin=protoc-gen-php=' . escapeshellarg($this->pluginExecutable);

        // Include paths
        $cmd[] = '--proto_path=' . escapeshellarg(__DIR__ . DIRECTORY_SEPARATOR . 'protos');
        if (!empty($input->getOption('include'))) {
            foreach($input->getOption('include') as $include) {
                // TODO: possibly remove realpath() - pm
                $include = realpath($include);
                $cmd[] = '--proto_path=' . escapeshellarg($include);
            }
        }

        // Convert proto files to absolute paths
        $protos = array();
        foreach ($input->getArgument('protos') as $proto) {
            $realpath = realpath($proto);
            if (FALSE === $realpath) {
                fputs(STDERR, "ERROR: File '$proto' does not exists");
                exit(1);
            }

            $protos[] = $realpath;
        }

        // Protoc will pass custom arguments to the plugin if they are given
        // before a colon character. ie: --php_out="foo=bar:/path/to/plugin"
        // We make use of it to pass arguments encoded as an URI query string

        $args = array();
        if ($input->getOption('comments')) {
            $args['comments'] = 1;
            // Protos are only needed for comments right now
            $args['protos'] = $protos;
        }
        //todo: test - pm
        if ($input->getOption('verbose')) {
            $args['verbose'] = 1;
        }
        if ($input->getOption('json')) {
            $args['json'] = 1;
        }
        if ($input->getOption('skipImported')) {
            $args['skip-imported'] = 1;
        }
        if ($input->getOption('define')) {
            $args['options'] = array();
            foreach($input->getOption('define') as $define) {
                $parts = explode('=', $define);
                $parts = array_filter(array_map('trim', $parts));
                if (count($parts) === 1) {
                    $parts[1] = 1;
                }
                $args['options'][$parts[0]] = $parts[1];
            }
        }
        if ($input->getOption('insertions')) {
            $args['options']['insertions'] = 1;
        }

        $cmd[] = '--php_out=' .
            escapeshellarg(
                http_build_query($args, '', '&') .
                ':' .
                $input->getOption('out')
            );

        // Add the chosen proto files to generate
        foreach ($protos as $proto) {
            $cmd[] = escapeshellarg($proto);
        }

        $cmdStr = implode(' ', $cmd);

        // Run command with stderr redirected to stdout
        passthru($cmdStr . ' 2>&1', $return);

        if ($return !== 0) {
            fputs(STDERR, PHP_EOL);
            fputs(STDERR, 'ERROR: protoc exited with an error (' . $return . ') when executed with: ' . PHP_EOL);
            fputs(STDERR, '  ' . implode(" \\\n    ", $cmd) . PHP_EOL);
            exit($return);
        }

        exit(0);
    }
}
