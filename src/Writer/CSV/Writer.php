<?php

declare(strict_types=1);

namespace OpenSpout\Writer\CSV;

use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Exception\IOException;
use OpenSpout\Common\Helper\EncodingHelper;
use OpenSpout\Writer\AbstractWriter;

final class Writer extends AbstractWriter
{
    /**
     * Number of rows to write before flushing.
     */
    public const FLUSH_THRESHOLD = 500;

    /** @var string Content-Type value for the header */
    protected static string $headerContentType = 'text/csv; charset=UTF-8';

    private Options $options;

    private int $lastWrittenRowIndex = 0;

    public function __construct(?Options $options = null)
    {
        $this->options = $options ?? new Options();
    }

    /**
     * Opens the CSV streamer and makes it ready to accept data.
     */
    protected function openWriter(): void
    {
        if ($this->options->SHOULD_ADD_BOM) {
            // Adds UTF-8 BOM for Unicode compatibility
            fwrite($this->filePointer, EncodingHelper::BOM_UTF8);
        }
    }

    /**
     * Adds a row to the currently opened writer.
     *
     * @param Row $row The row containing cells and styles
     *
     * @throws IOException If unable to write data
     */
    protected function addRowToWriter(Row $row): void
    {
        $cells = array_map(static function (Cell $value): string {
            return (string) $value->getValue();
        }, $row->getCells());

        $wasWriteSuccessful = fputcsv(
            $this->filePointer,
            $cells,
            $this->options->FIELD_DELIMITER,
            $this->options->FIELD_ENCLOSURE,
            ''
        );
        if (false === $wasWriteSuccessful) {
            throw new IOException('Unable to write data');
        }

        ++$this->lastWrittenRowIndex;
        if (0 === $this->lastWrittenRowIndex % self::FLUSH_THRESHOLD) {
            fflush($this->filePointer);
        }
    }

    /**
     * Closes the CSV streamer, preventing any additional writing.
     * If set, sets the headers and redirects output to the browser.
     */
    protected function closeWriter(): void
    {
        $this->lastWrittenRowIndex = 0;
    }
}
