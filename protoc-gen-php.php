#!/usr/bin/env php
<?php
// The MIT License
//
// Copyright (c) 2011 Iván -DrSlump- Montes
//
// Permission is hereby granted, free of charge, to any person obtaining a copy
// of this software and associated documentation files (the "Software"), to deal
// in the Software without restriction, including without limitation the rights
// to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
// copies of the Software, and to permit persons to whom the Software is
// furnished to do so, subject to the following conditions:
//
// The above copyright notice and this permission notice shall be included in
// all copies or substantial portions of the Software.
//
// THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
// IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
// FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
// AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
// LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
// OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
// THE SOFTWARE.

use Symfony\Component\Console\Application;
use DrSlump\Protobuf\Compiler\ProcessProtoFileCommand;
use DrSlump\Protobuf\Compiler\CompileProtoFileCommand;
use DrSlump\Protobuf;

// Set up default timezone
date_default_timezone_set('GMT');

// Disable strict errors for the compiler
error_reporting(error_reporting() & ~E_STRICT);

require_once 'vendor/autoload.php';

// TODO: remove this and use composer autoloader? - pm
require_once 'library/DrSlump/Protobuf.php';
// Setup autoloader
\DrSlump\Protobuf::autoload();

try {
    $application = new Application();
    $compileCommand = new CompileProtoFileCommand();

    $application->add(new ProcessProtoFileCommand(null, __FILE__));
    $application->add($compileCommand);
    $application->setDefaultCommand($compileCommand->getName());
    $application->run();

    exit(0);
} catch(Exception $e) {
    fputs(STDERR, (string)$e . PHP_EOL);
    exit(1);
}
