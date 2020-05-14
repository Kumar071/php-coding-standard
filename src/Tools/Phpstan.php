<?php

declare(strict_types=1);

namespace Spaceemotion\PhpCodingStandard\Tools;

use Spaceemotion\PhpCodingStandard\Context;
use Spaceemotion\PhpCodingStandard\Formatter\File;
use Spaceemotion\PhpCodingStandard\Formatter\Result;
use Spaceemotion\PhpCodingStandard\Formatter\Violation;

class Phpstan extends Tool
{
    protected $name = 'phpstan';

    public function run(Context $context): bool
    {
        $output = [];

        if (
            $this->execute(self::vendorBinary($this->name), array_merge(
                [
                    'analyse',
                    '--error-format=json',
                    '--no-ansi',
                    '--no-interaction',
                    '--debug',
                ],
                $context->files
            ), $output, [$this, 'trackProgress']) === 0
        ) {
            return true;
        }

        $lastLine = $output[count($output) - 1];
        $json = self::parseJson($lastLine);
        $result = new Result();

        if ($json === []) {
            $match = [];

            if (preg_match('/(.*) in (.*?) on line (\d+)$/i', $lastLine, $match) === false) {
                return false;
            }

            $file = new File();

            $violation = new Violation();
            $violation->line = (int) $match[3];
            $violation->message = $match[1];
            $violation->tool = $this->name;

            $file->violations[] = $violation;

            $result->files[$match[2]] = $file;

            $context->addResult($result);

            return false;
        }

        foreach ($json['files'] as $filename => $details) {
            $file = new File();

            foreach ($details['messages'] as $message) {
                $violation = new Violation();
                $violation->line = (int) ($message['line'] ?? 0);
                $violation->message = $message['message'];
                $violation->tool = $this->name;

                $file->violations[] = $violation;
            }

            $result->files[$filename] = $file;
        }

        $context->addResult($result);

        return false;
    }

    protected function trackProgress(string $line): bool
    {
        $firstLetter = $line[0] ?? '';

        if (PHP_OS_FAMILY === 'Windows') {
            // TODO how are file paths on windows again?
            return $firstLetter !== '{';
        }

        return $firstLetter === '/';
    }
}
