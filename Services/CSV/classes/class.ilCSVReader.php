<?php

/**
 * This file is part of ILIAS, a powerful learning management system
 * published by ILIAS open source e-Learning e.V.
 *
 * ILIAS is licensed with the GPL-3.0,
 * see https://www.gnu.org/licenses/gpl-3.0.en.html
 * You should have received a copy of said license along with the
 * source code, too.
 *
 * If this is not the case or you just want to try ILIAS, you'll find
 * us at:
 * https://www.ilias.de
 * https://github.com/ILIAS-eLearning
 *
 *********************************************************************/

class ilCSVReader
{
    /**
     * @var resource
     */
    private $file_resource;
    private string $line_ends;
    private array $data = [];
    private string $separator = ';';
    private string $delimiter = '""';
    private int $length = 1024;

    private function parse(): void
    {
        $row = 0;

        while (($line = fgetcsv($this->file_resource, $this->length, $this->separator)) !== false) {
            $line_count = count($line);
            for ($col = 0; $col < $line_count; $col++) {
                $this->data[$row][$col] = $this->unquote($line[$col]);
            }

            ++$row;
        }
    }

    public function setSeparator(string $a_sep): void
    {
        $this->separator = $a_sep;
    }

    public function setDelimiter(string $a_del): void
    {
        $this->delimiter = $a_del;
    }

    public function setLength(int $a_length): void
    {
        $this->length = $a_length;
    }

    public function open(string $path_to_file): bool
    {
        $this->file_resource = fopen(ilUtil::stripSlashes($path_to_file), "r");

        if (!is_resource($this->file_resource)) {
            throw new RuntimeException('sould not open stream to ' . $path_to_file);
        }
        return true;
    }

    public function close(): bool
    {
        return fclose($this->file_resource);
    }

    public function getCsvAsArray(): array
    {
        $this->parse();

        return $this->data;
    }

    private function unquote(string $a_str): string
    {
        return str_replace($this->delimiter . $this->delimiter, $this->delimiter, $a_str);
    }
}
