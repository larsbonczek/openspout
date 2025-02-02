<?php

declare(strict_types=1);

namespace OpenSpout\Writer\CSV;

use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Exception\IOException;
use OpenSpout\Common\Helper\EncodingHelper;
use OpenSpout\TestUsingResource;
use OpenSpout\Writer\Exception\WriterNotOpenedException;
use OpenSpout\Writer\RowCreationHelper;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class WriterTest extends TestCase
{
    use RowCreationHelper;
    use TestUsingResource;

    public function testWriteShouldThrowExceptionIfCannotOpenFileForWriting(): void
    {
        $this->expectException(IOException::class);

        $fileName = 'file_that_wont_be_written.csv';
        $this->createUnwritableFolderIfNeeded();
        $filePath = $this->getGeneratedUnwritableResourcePath($fileName);

        $writer = new Writer();
        @$writer->openToFile($filePath);
        $writer->addRow(Row::fromValues(['csv--11', 'csv--12']));
        $writer->close();
    }

    public function testWriteShouldThrowExceptionIfCallAddRowBeforeOpeningWriter(): void
    {
        $this->expectException(WriterNotOpenedException::class);

        $writer = new Writer();
        $writer->addRow(Row::fromValues(['csv--11', 'csv--12']));
        $writer->close();
    }

    public function testWriteShouldThrowExceptionIfCallAddRowsBeforeOpeningWriter(): void
    {
        $this->expectException(WriterNotOpenedException::class);

        $writer = new Writer();
        $writer->addRow(Row::fromValues(['csv--11', 'csv--12']));
        $writer->close();
    }

    public function testCloseShouldNoopWhenWriterIsNotOpened(): void
    {
        $fileName = 'test_double_close_calls.csv';
        $this->createGeneratedFolderIfNeeded($fileName);
        $resourcePath = $this->getGeneratedResourcePath($fileName);

        $writer = new Writer();
        $writer->close(); // This call should not cause any error

        $writer->openToFile($resourcePath);
        $writer->close();
        $writer->close(); // This call should not cause any error
        $this->expectNotToPerformAssertions();
    }

    public function testWriteShouldAddUtf8Bom(): void
    {
        $allRows = $this->createRowsFromValues([
            ['csv--11', 'csv--12'],
        ]);
        $writtenContent = $this->writeToCsvFileAndReturnWrittenContent($allRows, 'csv_with_utf8_bom.csv');

        self::assertStringStartsWith(EncodingHelper::BOM_UTF8, $writtenContent, 'The CSV file should contain a UTF-8 BOM');
    }

    public function testWriteShouldNotAddUtf8Bom(): void
    {
        $allRows = $this->createRowsFromValues([
            ['csv--11', 'csv--12'],
        ]);
        $writtenContent = $this->writeToCsvFileAndReturnWrittenContent($allRows, 'csv_no_bom.csv', ',', '"', false);

        self::assertStringNotContainsString(EncodingHelper::BOM_UTF8, $writtenContent, 'The CSV file should not contain a UTF-8 BOM');
    }

    public function testWriteShouldSupportNullValues(): void
    {
        $allRows = $this->createRowsFromValues([
            ['csv--11', null, 'csv--13'],
        ]);
        $writtenContent = $this->writeToCsvFileAndReturnWrittenContent($allRows, 'csv_with_null_values.csv');
        $writtenContent = $this->trimWrittenContent($writtenContent);

        self::assertSame('csv--11,,csv--13', $writtenContent, 'The null values should be replaced by empty values');
    }

    public function testWriteShouldNotSkipEmptyRows(): void
    {
        $allRows = $this->createRowsFromValues([
            ['csv--11', 'csv--12'],
            [],
            ['csv--31', 'csv--32'],
        ]);
        $writtenContent = $this->writeToCsvFileAndReturnWrittenContent($allRows, 'csv_with_empty_rows.csv');
        $writtenContent = $this->trimWrittenContent($writtenContent);

        self::assertSame("csv--11,csv--12\n\ncsv--31,csv--32", $writtenContent, 'Empty rows should be skipped');
    }

    public function testWriteShouldSupportCustomFieldDelimiter(): void
    {
        $allRows = $this->createRowsFromValues([
            ['csv--11', 'csv--12', 'csv--13'],
            ['csv--21', 'csv--22', 'csv--23'],
        ]);
        $writtenContent = $this->writeToCsvFileAndReturnWrittenContent($allRows, 'csv_with_pipe_delimiters.csv', '|');
        $writtenContent = $this->trimWrittenContent($writtenContent);

        self::assertSame("csv--11|csv--12|csv--13\ncsv--21|csv--22|csv--23", $writtenContent, 'The fields should be delimited with |');
    }

    public function testWriteShouldSupportCustomFieldEnclosure(): void
    {
        $allRows = $this->createRowsFromValues([
            ['This is, a comma', 'csv--12', 'csv--13'],
        ]);
        $writtenContent = $this->writeToCsvFileAndReturnWrittenContent($allRows, 'csv_with_pound_enclosures.csv', ',', '#');
        $writtenContent = $this->trimWrittenContent($writtenContent);

        self::assertSame('#This is, a comma#,csv--12,csv--13', $writtenContent, 'The fields should be enclosed with #');
    }

    public function testWriteShouldSupportedEscapedCharacters(): void
    {
        $allRows = $this->createRowsFromValues([
            ['"csv--11"', 'csv--12\\', 'csv--13\\\\', 'csv--14\\\\\\'],
        ]);
        $writtenContent = $this->writeToCsvFileAndReturnWrittenContent($allRows, 'csv_with_escaped_characters.csv');
        $writtenContent = $this->trimWrittenContent($writtenContent);

        self::assertSame('"""csv--11""",csv--12\\,csv--13\\\\,csv--14\\\\\\', $writtenContent, 'The \'"\' and \'\\\' characters should be properly escaped');
    }

    /**
     * @param Row[] $allRows
     */
    private function writeToCsvFileAndReturnWrittenContent(
        array $allRows,
        string $fileName,
        ?string $fieldDelimiter = null,
        ?string $fieldEnclosure = null,
        ?bool $shouldAddBOM = null
    ): string {
        $this->createGeneratedFolderIfNeeded($fileName);
        $resourcePath = $this->getGeneratedResourcePath($fileName);

        $options = new Options();
        if (null !== $fieldDelimiter) {
            $options->FIELD_DELIMITER = $fieldDelimiter;
        }
        if (null !== $fieldEnclosure) {
            $options->FIELD_ENCLOSURE = $fieldEnclosure;
        }
        if (null !== $shouldAddBOM) {
            $options->SHOULD_ADD_BOM = $shouldAddBOM;
        }
        $writer = new Writer($options);
        $writer->openToFile($resourcePath);
        $writer->addRows($allRows);
        $writer->close();

        $file_get_contents = file_get_contents($resourcePath);
        self::assertNotFalse($file_get_contents);

        return $file_get_contents;
    }

    private function trimWrittenContent(string $writtenContent): string
    {
        // remove line feeds and UTF-8 BOM
        return trim($writtenContent, PHP_EOL.EncodingHelper::BOM_UTF8);
    }
}
