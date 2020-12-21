<?php
require 'db/Connect.php';
require 'vendor/autoload.php';

use GuzzleHttp\Client;

class Company
{
    /**
     * @var PDO
     */
    private $db;

    /**
     * @var Client
     */
    private $client;

    public function __construct()
    {
        $this->client = new Client();
        $this->db = Connect::PDO();
        $this->populateCompanies();
        $this->getFoundedYear();
    }

    private function getFoundedYear()
    {

        $companies = $this->db->query("SELECT * FROM `companyfounded` WHERE `year`=0")
            ->fetchAll(PDO::FETCH_ASSOC);

        foreach ($companies as $company) {
            $dataEncoded = str_replace(" ", "+", $company['name']);
            $googleUrl = "https://www.google.com/search?q=$dataEncoded+founded&oq=$dataEncoded+founded";
            echo "Geeting company $company[name] content from $googleUrl \n";
            $content = $this->getContent($googleUrl);
            preg_match_all('/founded.*([\d]+?)/isU', $content, $matches);
            if ($matches[1] && count($matches[1]) > 0) {
                echo "found: \n";
                print_r($matches[1]);
                $years = array_filter($matches[1], function($v) {
                    return $v < 2020 && $v > 1800 ;
                });
                if(count($years) > 0){
                    print_r($years);
                    $sql = "UPDATE `companyfounded` SET `year`=".min($years)." WHERE id=".$company['id'];
                    echo "$sql\n";
                    $this->db->query($sql);
                }
                else {
                    echo "Year not found\n";
                }
            } else {
                echo "Nothing found with the regex\n";
            }
            sleep(3);
        }
    }

    /**
     * get page content
     */
    private function getContent(string $url): string
    {
        $res = $this->client->request('GET', $url);
        return $res->getBody();
    }

    private function populateCompanies()
    {
        $companies = $this->db->query("SELECT DISTINCT company, country FROM `balance` WHERE company NOT LIKE '%-%'")
            ->fetchAll(PDO::FETCH_ASSOC);

        foreach ($companies as $company) {
            $add = sprintf(
                "INSERT INTO `companyfounded` (`country`,`name`) VALUES ('%s', '%s');",
                $company['country'],
                trim(str_replace(['0 biotechnology', 'online services', 'services'], "", $company['company']))
            );
            echo "$add\n";

            $this->db->query($add);
        }

        $companies = $this->db->query("SELECT DISTINCT company, country FROM `balance` WHERE company LIKE '%-%'")
            ->fetchAll(PDO::FETCH_ASSOC);

        foreach ($companies as $company) {
            $companyPart = explode("-", $company['company']);
            $newCompany = count($companyPart) > 2 ? $companyPart[0] . ' ' . $companyPart[1] : $companyPart[0];

            $add = sprintf(
                "INSERT INTO `companyfounded` (`country`,`name`) VALUES ('%s','%s');",
                $company['country'],
                trim($newCompany)
            );

            echo "$add\n";

            $this->db->query($add);
        }
    }
}

new Company();
