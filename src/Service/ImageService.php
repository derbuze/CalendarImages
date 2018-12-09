<?php
/**
 * Created by PhpStorm.
 * User: markus
 * Date: 07.05.18
 * Time: 23:18
 */

namespace App\Service;


use DateTime;
use DirectoryIterator;
use Exception;
use Symfony\Component\Console\Helper\ProgressBar;

class ImageService
{
    /** @var array $failedFiles */
    private $failedFiles = array();

    /** @var array $succeededFiled*/
    private $succeededFiles = array();

    /** @var integer */
    private $fileCount;

    /** @var string */
    private $source;

    /** @var string */
    private $target;

    /** @var string */
    private $year;

    /** @var integer */
    private $currentFileNumber;

    /** @var ProgressBar */
    private $progressBar;

    /**
     * @return int
     */
    public function getFileCount(): int
    {
        return $this->fileCount;
    }

    /**
     * @param int $fileCount
     */
    public function setFileCount(int $fileCount)
    {
        $this->fileCount = $fileCount;
    }


    /**
     * @return string
     */
    public function getSource(): string
    {
        return $this->source;
    }

    /**
     * @param string $source
     */
    public function setSource(string $source)
    {
        $this->source = $source;
    }

    /**
     * @return string
     */
    public function getTarget(): string
    {
        return $this->target;
    }

    /**
     * @param string $target
     */
    public function setTarget(string $target)
    {
        $this->target = $target;
    }

    /**
     * @return string
     */
    public function getYear(): string
    {
        return $this->year;
    }

    /**
     * @param string $year
     */
    public function setYear(string $year)
    {
        $this->year = $year;
    }

    /**
     * @return array
     */
    public function getFailedFiles(): array
    {
        return $this->failedFiles;
    }

    /**
     * @param array $failedFiles
     */
    public function setFailedFiles(array $failedFiles)
    {
        $this->failedFiles = $failedFiles;
    }

    /**
     * @return array
     */
    public function getSucceededFiles(): array
    {
        return $this->succeededFiles;
    }

    /**
     * @param array $succeededFiles
     */
    public function setSucceededFiles(array $succeededFiles)
    {
        $this->succeededFiles = $succeededFiles;
    }

    /**
     * @return int
     */
    public function getCurrentFileNumber(): int
    {
        return $this->currentFileNumber;
    }

    /**
     * @param int $currentFileNumber
     */
    public function setCurrentFileNumber(int $currentFileNumber)
    {
        $this->currentFileNumber = $currentFileNumber;
    }

    /**
     * @return ProgressBar
     */
    public function getProgressBar(): ProgressBar
    {
        return $this->progressBar;
    }

    /**
     * @param ProgressBar $progressBar
     */
    public function setProgressBar(ProgressBar $progressBar)
    {
        $this->progressBar = $progressBar;
    }

    /**
     * Function scans folders and sorts images by original creation date
     */
    public function sortImages()
    {
        $this->resetTargetFolders();
        $this->createTargetFolders();
        $directoryIterator = new DirectoryIterator($this->getSource());

        foreach ($directoryIterator as $fileInfo) {
            if (!$fileInfo->isDot()) {

                $this->progressBar->advance();

                $month = null;
                $sourcePath = $this->getSource() . '/' . $fileInfo->getFilename();
                $exifData = @exif_read_data($sourcePath, 'FILE', true);
                if (is_array($exifData)) {
                    try {
                        $creationDate = $this->getCreationDate($exifData);
                        $year = $creationDate->format('Y');
                        if ($year === $this->getYear()) {
                            $month = $creationDate->format('m');
                            $this->succeededFiles[] = array($fileInfo->getFilename(), $month);
                            $this->copyFile($sourcePath, $year, $month, $fileInfo);
                            // TODO write log info
                        }

                    } catch (Exception $e) {
                        $this->failedFiles[] = array($fileInfo->getFilename(), 'undefined');
                        $this->copyFile($sourcePath, 'undefined', 'undefined', $fileInfo);
                        // TODO write log info
                    }
                } else {
                    $this->failedFiles[] = array($fileInfo->getFilename(), 'undefined');
                    $this->copyFile($sourcePath, 'undefined', 'undefined', $fileInfo);
                }
            }
        }
    }

    /**
     * Create target folder for each month
     */
    private function createTargetFolders()
    {

        if (!is_dir($this->getTarget())) {
            mkdir($this->getTarget());
        }

        if (!is_dir($this->getTarget().'/'.$this->getYear())) {
            mkdir($this->getTarget().'/'.$this->getYear());
        }

        $months = array('01', '02', '03', '04', '05', '06', '07', '08', '09', '10', '11', '12');

        foreach ($months as $month) {
            if (!is_dir($this->getTarget().'/'.$this->getYear().'/'.$month)) {
                mkdir($this->getTarget().'/'.$this->getYear().'/'.$month);
            }
        }
        // TODO Add log file info
    }

    /**
     * Create CSV file for succeeded and failed files
     *
     * @param array $files
     * @param string $csvFileName
     */
    public function writeCsv(array $files, string $csvFileName)
    {
        if (count($files) > 0) {

            $fp = fopen($csvFileName, 'w');
            fputcsv($fp, array('filename', 'target folder'), ';', '"');
            foreach ($files as $fields) {
                fputcsv($fp, $fields, ';', '"');
            }
            fclose($fp);
        }
        // TODO Add log file info
    }

    /**
     * Remove existing target folder
     */
    public function resetTargetFolders()
    {
        $this->removeDirectory($this->getTarget().'/'.$this->getYear());
        #if (is_file(self::SUCCEEDED_FILES_CSV_FILENAME)) {
        #    unlink(self::SUCCEEDED_FILES_CSV_FILENAME);
        #}
        #if (is_file(self::FAILED_FILES_CSV_FILENAME)) {
        #    unlink(self::FAILED_FILES_CSV_FILENAME);
        #}
    }

    /**
     * Function removes given folder and all contained sub-folders and files
     *
     * @param $path
     */
    private function removeDirectory($path)
    {
        $files = glob($path . '/*');
        foreach ($files as $file) {
            is_dir($file) ? $this->removeDirectory($file) : unlink($file);
        }
        if (is_dir($path)) {
            rmdir($path);
        }
        // TODO Add log file info
    }

    /**
     * @param string $sourcePath
     * @param string $year
     * @param string $month
     * @param DirectoryIterator $fileInfo
     */
    private function copyFile(string $sourcePath, string $year, string $month, DirectoryIterator $fileInfo)
    {
        $destinationPath = $this->getTarget() . '/' . $year . '/' . $month . '/' . $fileInfo->getFilename() ;

        if (!is_dir($this->getTarget().'/'. $year)) {
            mkdir($this->getTarget().'/'. $year);
        }

        if (!is_dir($this->getTarget().'/'. $year . '/' . $month)) {
            mkdir($this->getTarget().'/'. $year . '/' . $month);
        }

        try {
            copy($sourcePath, $destinationPath);
        } catch (Exception $e) {
            // TODO log exception
        }
    }

    /**
     * @param array $exifData
     * @return array|DateTime|mixed|null
     * @throws Exception
     */
    private function getCreationDate(array $exifData) : DateTime
    {
        if (array_key_exists('EXIF', $exifData) &&
            array_key_exists('DateTimeOriginal', $exifData['EXIF'])) {
            $dateInfo = ($exifData['EXIF']['DateTimeOriginal']);

            $date = explode(' ', $dateInfo);
            $date = str_replace(':', '-', $date[0]); // date format in EXIF is like 2018:01:31
            return new DateTime($date);

        } else {
            throw new Exception('Date not found');
        }
    }

    /**
     * Function scans folders and counts files.
     */
    public function countImages() : int
    {
        $this->resetTargetFolders();
        $this->createTargetFolders();
        $directoryIterator = new DirectoryIterator($this->getSource());

        $this->fileCount = 0;

        foreach ($directoryIterator as $fileInfo) {
            if (!$fileInfo->isDot()) {

                $this->fileCount++;

            }
        }

        return $this->fileCount;
    }
}
