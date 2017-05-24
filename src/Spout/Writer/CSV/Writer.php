<?php

namespace Box\Spout\Writer\CSV;

use Box\Spout\Writer\AbstractWriter;
use Box\Spout\Common\Exception\IOException;
use Box\Spout\Common\Helper\EncodingHelper;
use Box\Spout\Writer\Common\Options;

/**
 * Class Writer
 * This class provides support to write data to CSV files
 *
 * @package Box\Spout\Writer\CSV
 */
class Writer extends AbstractWriter
{
    /** Number of rows to write before flushing */
    const FLUSH_THRESHOLD = 500;

    /** @var string Content-Type value for the header */
    protected static $headerContentType = 'text/csv; charset=UTF-8';

    /** @var int */
    protected $lastWrittenRowIndex = 0;

    /**
     * Sets the field delimiter for the CSV
     *
     * @api
     * @param string $fieldDelimiter Character that delimits fields
     * @return Writer
     */
    public function setFieldDelimiter($fieldDelimiter)
    {
        $this->optionsManager->setOption(Options::FIELD_DELIMITER, $fieldDelimiter);
        return $this;
    }

    /**
     * Sets the field enclosure for the CSV
     *
     * @api
     * @param string $fieldEnclosure Character that enclose fields
     * @return Writer
     */
    public function setFieldEnclosure($fieldEnclosure)
    {
        $this->optionsManager->setOption(Options::FIELD_ENCLOSURE, $fieldEnclosure);
        return $this;
    }

    /**
     * Set if a BOM has to be added to the file
     *
     * @api
     * @param bool $shouldAddBOM
     * @return Writer
     */
    public function setShouldAddBOM($shouldAddBOM)
    {
        $this->optionsManager->setOption(Options::SHOULD_ADD_BOM, (bool) $shouldAddBOM);
        return $this;
    }

    /**
     * Opens the CSV streamer and makes it ready to accept data.
     *
     * @return void
     */
    protected function openWriter()
    {
        if ($this->optionsManager->getOption(Options::SHOULD_ADD_BOM)) {
            // Adds UTF-8 BOM for Unicode compatibility
            $this->globalFunctionsHelper->fputs($this->filePointer, EncodingHelper::BOM_UTF8);
        }
    }

    /**
     * Adds data to the currently opened writer.
     *
     * @param  array $dataRow Array containing data to be written.
     *          Example $dataRow = ['data1', 1234, null, '', 'data5'];
     * @param \Box\Spout\Writer\Style\Style $style Ignored here since CSV does not support styling.
     * @return void
     * @throws \Box\Spout\Common\Exception\IOException If unable to write data
     */
    protected function addRowToWriter(array $dataRow, $style)
    {
        $fieldDelimiter = $this->optionsManager->getOption(Options::FIELD_DELIMITER);
        $fieldEnclosure = $this->optionsManager->getOption(Options::FIELD_ENCLOSURE);

        $wasWriteSuccessful = $this->globalFunctionsHelper->fputcsv($this->filePointer, $dataRow, $fieldDelimiter, $fieldEnclosure);
        if ($wasWriteSuccessful === false) {
            throw new IOException('Unable to write data');
        }

        $this->lastWrittenRowIndex++;
        if ($this->lastWrittenRowIndex % self::FLUSH_THRESHOLD === 0) {
            $this->globalFunctionsHelper->fflush($this->filePointer);
        }
    }

    /**
     * Closes the CSV streamer, preventing any additional writing.
     * If set, sets the headers and redirects output to the browser.
     *
     * @return void
     */
    protected function closeWriter()
    {
        $this->lastWrittenRowIndex = 0;
    }
}
