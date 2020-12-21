<?php
// error_reporting(E_ERROR | E_PARSE);
require 'db/Connect.php';


class WorkerGenerateFinalRaport
{
    /**
     * @var PDO
     */
    private $db;
    public function __construct()
    {
        $this->db = Connect::PDO();
        // $this->updateCompanyName();
        $this->mergeData();
        $this->addCompanyYear();
    }

    private function addCompanyYear()
    {
        $companies = $this->db->query("SELECT * FROM `companyfounded` WHERE `year`!=0")
            ->fetchAll(PDO::FETCH_ASSOC);
        foreach ($companies as $company) {
            print_r($company);

            $sql = "UPDATE `final_data` SET `founded_year`=" . $company['year'] . " WHERE `company`=:name AND `country`=:country";
            echo "$sql\n\n";

            $stmt = $this->db->prepare($sql);
            if (!$stmt) {
                echo "\nPDO::errorInfo():\n";
                print_r($this->db->errorInfo());
                exit();
            }
            $stmt->execute([
                'name' => $company['name'],
                'country' => $company['country']
            ]);
        }

        echo "Done\n";
    }

    private function mergeData()
    {
        $tables = [
            "balance",
            "cashflow",
            "income",
            "keymetrics"
        ];

        $ignoreColumnsForUpdate = [
            "id",
            "country",
            "company",
            "year"
        ];

        foreach ($tables as $table) {
            $results = $this->db->query("SELECT * FROM `$table`")
                ->fetchAll(PDO::FETCH_ASSOC);

            $numRows = count($results);
            $x = 0;
            foreach ($results as $data) {
                $x++;
                echo "Table $table --------- $x / $numRows ---------------\n";
                print_r($data);
                $stmt = $this->db->prepare("SELECT `id` FROM `final_data` WHERE `company`=:company AND `year`=:year");
                $stmt->execute([
                    'company' => $data['company'],
                    'year' => $data['year']
                ]);
                $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

                if (count($records) > 1) {
                    echo "WARINING: multiple records found, will skip\n";
                    print_r($records);
                } else {
                    if (count($records) == 1) {
                        foreach ($ignoreColumnsForUpdate as $ignoreColumn) {
                            unset($data[$ignoreColumn]);
                        }

                        $data = $this->reformatData($data);

                        $fields = [];
                        foreach (array_keys($data) as $key) {
                            $fields[] = "$key=:$key";
                        }

                        $sql = "UPDATE `final_data` SET " . implode(", ", $fields) . " WHERE id=" . $records[0]['id'];
                    } else {
                        unset($data['id']);
                        $data = $this->reformatData($data);

                        $sql = "INSERT INTO `final_data` (" . implode(", ", array_keys($data)) . ") VALUES (:" . implode(", :", array_keys($data)) . ")";
                    }

                    echo "$sql\n\n";

                    $stmt = $this->db->prepare($sql);
                    if (!$stmt) {
                        echo "\nPDO::errorInfo():\n";
                        print_r($this->db->errorInfo());
                        exit();
                    }
                    $stmt->execute($data);
                }
            }
        }

        echo "done\n";
    }

    private function reformatData(array $data): array
    {
        $newData = [];
        foreach ($data as $key => $value) {
            $newValue = $value == "--" || $value == "-" ? "" : $value;
            if (substr($value, 0, 1) === '(') {
                $newValue = "-" . str_replace(["(", ")"], "", $newValue);
            }

            $newData[$key] = $newValue;
        }

        return $newData;
    }

    private function updateCompanyName()
    {
        $tables = [
            "balance",
            "cashflow",
            "income",
            "keymetrics"
        ];

        foreach ($tables as $table) {
            $companies = $this->db->query("SELECT DISTINCT company FROM `$table` WHERE company NOT LIKE '%-%'")
                ->fetchAll(PDO::FETCH_ASSOC);

            foreach ($companies as $company) {
                $newCompany = trim(str_replace(['0 biotechnology', 'online services', 'services'], "", $company['company']));
                $this->updateCompany($newCompany, $company['company'], $table);
            }

            $companies = $this->db->query("SELECT DISTINCT company FROM `$table` WHERE company LIKE '%-%'")
                ->fetchAll(PDO::FETCH_ASSOC);

            foreach ($companies as $company) {
                $companyPart = explode("-", $company['company']);
                $newCompany = count($companyPart) > 2 ? $companyPart[0] . ' ' . $companyPart[1] : $companyPart[0];
                $this->updateCompany($newCompany, $company['company'], $table);
            }
        }
    }

    private function updateCompany(string $newCompany, string $company, $table)
    {
        $stmt = $this->db->prepare("UPDATE `$table` SET company=:company1 WHERE company=:company2");
        $stmt->execute([
            'company1' => $newCompany,
            'company2' => $company
        ]);
        echo "Updating company " . $company . "\n";
    }
}



new WorkerGenerateFinalRaport();
