<?php
error_reporting(E_ERROR | E_PARSE);
require 'db/Connect.php';
require 'vendor/autoload.php';

use \PhpOffice\PhpSpreadsheet\IOFactory;

class ParseXLS
{

    private $fileNotFound = [];

    /**
     * @var PDO
     */
    private $db;

    private $path = "./xls/*";

    private $files = [
        "balance.xls",
        "cash flow.xls",
        "income.xls",
        "key metrics.xls"
    ];

    public function __construct()
    {
        $this->db = Connect::PDO();
        $this->run();
    }

    private function run()
    {
        foreach (glob($this->path) as $dbCountry) {
            $country = $this->getCountry($dbCountry);
            foreach (glob($dbCountry . "/*") as $companyPath) {
                $company = $this->getCompany($dbCountry, $companyPath);
                $this->readCompanyDir($companyPath, $country, $company);
            }
        }

        if (count($this->fileNotFound) > 0) {
            echo "\n\n\n";
            echo "WARNING: " . count($this->fileNotFound) . " files were not found \n";
            echo "check fileNotFound.js for more details \n";

            file_put_contents("fileNotFound.js", json_encode($this->fileNotFound));
        }
    }

    private function getCountry(string $dbCountry): string
    {
        $strings = explode("-", $dbCountry);

        return ucfirst(trim($strings[1]));
    }

    private function getCompany(string $initialPath, string $companyPath): string
    {
        $strings = explode($initialPath . "/", $companyPath);

        return ucfirst(trim($strings[1]));
    }

    private function readCompanyDir(string $dirPath, string $country, string $company)
    {
        echo "\n---------------------------------------\n";
        echo "Parsing company `$company` from country `$country` ------------------\n\n";

        foreach ($this->files as $file) {
            switch ($file) {
                case "balance.xls":
                    $otherFiles = [
                        "balance sheet.xls",
                        "bal;ance.xls",
                        "balace.xls",
                        "ba;ance.xls",
                        "baalnce.xls"
                    ];

                    echo "Reading file `$file` \n";

                    $fileData = $this->getFileContent($dirPath, $file, $otherFiles);

                    //fields to search in xls array
                    $arraysWith = [
                        'Cash',
                        'Cash & Equivalents',
                        'Total Current Assets',
                        'Total Assets',
                        'Total Current Liabilities',
                        'Total Long Term Debt',
                        'Total Debt',
                        'Total Equity',
                        'Full-Time Employees',
                        'Part-Time Employees'
                    ];
                    $this->saveXlsData($fileData, $country, $company, $arraysWith, "balance");

                    unset($fileData);
                    break;
                case "cash flow.xls":
                    $otherFiles = [
                        "casg flow.xls",
                        "cahs flow.xls",
                        "acsh flow.xls",
                        "cas flow.xls",
                        "cash floe.xls"
                    ];
                    $fileData = $this->getFileContent($dirPath, $file, $otherFiles);

                    //fields to search in xls array
                    $arraysWith = [
                        'Cash from Operating Activities',
                        'Cash from Investing Activities',
                        'Total Cash Dividends Paid',
                        'Cash from Financing Activities',
                        'Free Cash Flow'
                    ];
                    $this->saveXlsData($fileData, $country, $company, $arraysWith, "cashFlow");

                    unset($fileData);
                    break;
                case "income.xls":
                    $otherFiles = [];
                    $fileData = $this->getFileContent($dirPath, $file, $otherFiles);

                    //fields to search in xls array
                    $arraysWith = [
                        'Net Income'
                    ];
                    $this->saveXlsData($fileData, $country, $company, $arraysWith, "income");

                    unset($fileData);
                    break;
                case "key metrics.xls":
                    $otherFiles = [
                        "key emtrics.xls",
                        "ratios key metrics.xls",
                        "ratio skey metrics.xls",
                        "ratio key metrics.xls",
                        "ratios key metris.xls",
                        "key metrcis.xls",
                        "ratios key metricd.xls",
                        "keymetrics.xls",
                        "kley metrics.xls",
                        "key metyrics.xls"
                    ];
                    $fileData = $this->getFileContent($dirPath, $file, $otherFiles);

                    //fields to search in xls array
                    $arraysWith = [
                        'Effective Tax Rate',
                        'ROE',
                        'Quick Ratio',
                        'Current Ratio',
                        'ROIC'
                    ];
                    $this->saveXlsData($fileData, $country, $company, $arraysWith, "keyMetrics");

                    unset($fileData);
                    break;

                default:
                    echo "Error: File $file not supported";
                    break;
            }
        }
    }

    private function getFileContent(string $dirPath, string $initialName, array $otherNames)
    {
        if (file_exists("$dirPath/$initialName")) {
            echo "$dirPath/$initialName\n";

            return $this->openFile("$dirPath/$initialName");
        } else {
            foreach ($otherNames as $potentialFile) {
                if (file_exists("$dirPath/$potentialFile")) {
                    echo "$dirPath/$potentialFile\n";

                    return $this->openFile("$dirPath/$potentialFile");
                }
            }
        }

        $this->fileNotFound[] = "$dirPath/$initialName";
        return false;
    }

    private function openFile(string $filePath): array
    {
        $spreadsheet = IOFactory::load($filePath);
        $worksheet = $spreadsheet->getActiveSheet();

        // $data = $worksheet->toArray(null, true, true, true);
        $data = array(1, $worksheet->toArray(null, true, true, true));

        // print_r($data);
        // exit();

        $xlsData = $data[1];

        //free memory
        unset($spreadsheet);
        unset($worksheet);
        unset($data);

        return $xlsData;
    }

    private function saveXlsData(array $fileData, string $country, string $company, array $arraysWith, string $tableName)
    {
        echo "Saving data... ";

        $years = $fileData[3];
        unset($years['A']);
        if(trim($years['B']) == "Industry Median"){
            unset($years['B']);
        }

        foreach ($fileData as $xlsData) {
            $type = trim($xlsData['A']);
            if (in_array($type,  $arraysWith)) {
                unset($xlsData['A']);
                $dataToInsert = [];

                foreach ($xlsData as $key => $val) {
                    if (isset($years[$key])) {
                        $dataToInsert[$years[$key]] = trim($val);
                    }
                }

                $this->addData($tableName, $country, $company, $type, $dataToInsert);

                unset($dataToInsert);
            }
        }

        echo "Done\n";

        unset($fileData);
        unset($years);
    }

    private function addData(string $table, string $country, string $company, string $type, array $dataToInsert)
    {
        $dinamicColumn = str_replace("&", "And", str_replace([" ", "-"], "_", $type));

        foreach ($dataToInsert as $year => $value) {
            $stmt = $this->db->prepare("SELECT id FROM `$table` WHERE country=:country AND company=:company AND year=:year");
            $stmt->execute([
                'country' => $country,
                'company' => $company,
                'year' => $year
            ]);
            $check = $stmt->fetch();
            if (!$check) {
                $add = sprintf(
                    "INSERT INTO `$table` (`country`, `company`, `year`, `$dinamicColumn`) VALUES ('%s', '%s', '%s', '%s');",
                    $country,
                    $company,
                    $year,
                    $value
                );

                $this->db->query($add);
            } else {
                $update = sprintf(
                    "UPDATE `$table` SET `country`='%s', `company`='%s', `year`='%s', `$dinamicColumn`='%s' WHERE id=%d;",
                    $country,
                    $company,
                    $year,
                    $value,
                    $check['id']
                );

                $this->db->query($update);
            }
        }

        unset($dataToInsert);
    }
}


$start = new ParseXLS();
