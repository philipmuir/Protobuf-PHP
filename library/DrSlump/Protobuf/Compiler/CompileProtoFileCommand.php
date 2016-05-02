<?php

namespace DrSlump\Protobuf\Compiler;

use DrSlump\Protobuf;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CompileProtoFileCommand extends Command
{

    protected function configure()
    {
        $this
            ->setName('proto:compile')
            ->setDescription('Compiles a protofile from STDIN.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $stdin = '';

        // PHP doesn't implement non-blocking stdin on Windows
        // https://bugs.php.net/bug.php?id=34972
        $isWin = 'WIN' === strtoupper(substr(PHP_OS, 0, 3));
        if (!$isWin) {

            // Open STDIN in non-blocking mode
            stream_set_blocking(STDIN, FALSE);

            // Loop until STDIN is closed or we've waited too long for data
            $cnt = 0;
            while (!feof(STDIN) && $cnt++ < 10) {
                // give protoc some time to feed the data
                usleep(10000);
                // read the bytes
                $bytes = fread(STDIN, 1024);
                if (strlen($bytes)) {
                    $cnt = 0;
                    $stdin .= $bytes;
                }
            }

            // If on windows and no arguments were given
        } else if ($_SERVER['argc'] < 2) {
            $stdin = fread(STDIN, 1024 * 1024);
        }

        // We have data from stdin so compile it
        try {
            // Create a compiler interface
            $comp = new Protobuf\Compiler(true);
            echo $comp->compile($stdin);
            exit(0);
        } catch(\Exception $e) {
            fputs(STDERR, 'ERROR: ' . $e->getMessage() . PHP_EOL);
            fputs(STDERR, $e->getTraceAsString() . PHP_EOL);
            exit(255);
        }
    }
}
