<?php

namespace Hail\Excel\Writer\CSV;

use Hail\Excel\Writer\AbstractWriter;
use Hail\Excel\Common\Exception\IOException;
use Hail\Excel\Common\Helper\EncodingHelper;

/**
 * Class Writer
 * This class provides support to write data to CSV files
 *
 * @package Hail\Excel\Writer\CSV
 */
class Writer extends AbstractWriter
{
    /** Number of rows to write before flushing */
    const FLUSH_THRESHOLD = 500;

    /** @var string Content-Type value for the header */
    protected static $headerContentType = 'text/csv; charset=UTF-8';

    /** @var string Defines the character used to delimit fields (one character only) */
    protected $fieldDelimiter = ',';

    /** @var string Defines the character used to enclose fields (one character only) */
    protected $fieldEnclosure = '"';

    /** @var int */
    protected $lastWrittenRowIndex = 0;

    /** @var bool */
    protected $shouldAddBOM = true;

    /**
     * Sets the field delimiter for the CSV
     *
     * @api
     * @param string $fieldDelimiter Character that delimits fields
     * @return Writer
     */
    public function setFieldDelimiter($fieldDelimiter)
    {
        $this->fieldDelimiter = $fieldDelimiter;
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
        $this->fieldEnclosure = $fieldEnclosure;
        return $this;
    }

    /**
     * Set if a BOM has to be added to the file
     *
     * @param bool $shouldAddBOM
     * @return Writer
     */
    public function setShouldAddBOM($shouldAddBOM)
    {
        $this->shouldAddBOM = (bool) $shouldAddBOM;
        return $this;
    }

    /**
     * Opens the CSV streamer and makes it ready to accept data.
     *
     * @return void
     */
    protected function openWriter()
    {
        if ($this->shouldAddBOM) {
            // Adds UTF-8 BOM for Unicode compatibility
	        fwrite($this->filePointer, EncodingHelper::BOM_UTF8);
        }
    }

    /**
     * Adds data to the currently opened writer.
     *
     * @param  array $dataRow Array containing data to be written.
     *          Example $dataRow = ['data1', 1234, null, '', 'data5'];
     * @param \Hail\Excel\Writer\Style\Style $style Ignored here since CSV does not support styling.
     * @return void
     * @throws \Hail\Excel\Common\Exception\IOException If unable to write data
     */
    protected function addRowToWriter(array $dataRow, $style)
    {
        $wasWriteSuccessful = fputcsv($this->filePointer, $dataRow, $this->fieldDelimiter, $this->fieldEnclosure);
        if ($wasWriteSuccessful === false) {
            throw new IOException('Unable to write data');
        }

        $this->lastWrittenRowIndex++;
        if ($this->lastWrittenRowIndex % self::FLUSH_THRESHOLD === 0) {
            fflush($this->filePointer);
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
