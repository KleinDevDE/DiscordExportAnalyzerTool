<?php
header('Content-type: text/html; charset=utf-8');


class DiscordExportAnalyzer
{
    /**
     * @var bool
     */
    private $verbose;

    /**
     * @var array
     */
    private $arrMessages = [];
    private $arrIDMapping = [];

    private $arrAccountData = [];

    public function __construct($sourceFolder)
    {
        define("SOURCE_FOLDER", $sourceFolder);
        $this->arrMessages = $this->getAllMessagesAsArray();
        $this->arrIDMapping = $this->getIDMapping();
        $this->arrAccountData = $this->getAccountData();
    }

    private function getAccountData(): array
    {
        return json_decode(file_get_contents(SOURCE_FOLDER . DIRECTORY_SEPARATOR . "account" . DIRECTORY_SEPARATOR . "user.json"), true);
    }

    private function getIDMapping(): array
    {
        return json_decode(file_get_contents(SOURCE_FOLDER . DIRECTORY_SEPARATOR . "messages" . DIRECTORY_SEPARATOR . "Index.json"), true);
    }

    private function getAllMessagesAsArray(): array
    {
        $folders = scandir(SOURCE_FOLDER . DIRECTORY_SEPARATOR . "messages");
        $arrResult = [];
        foreach ($folders as $folder) {
            $file = SOURCE_FOLDER . DIRECTORY_SEPARATOR . "messages" . DIRECTORY_SEPARATOR . $folder . DIRECTORY_SEPARATOR . "messages.csv";
            if (!is_file($file)) {
                continue;
            }
            $arrTmp = array_map('str_getcsv', file($file));
            if (empty($arrTmp) || $arrTmp == NULL || $arrTmp[0] == NULL) {
                $arrTmp[$folder] = array(NULL, NULL, "Hier fand keine Konversation statt.", NULL);
                continue;
            }
            array_shift($arrTmp);

            //Detect broken arrays (Multiline messages causes broken arrays) and fix it by adding messagecontent to the last not-broken array
            $lastIndexWithRealMessageArray = 0;
            foreach ($arrTmp as $index => $arrMessage) {
                if ($this->verbose) {
                    $this->echoDebug($lastIndexWithRealMessageArray);
                    $this->echoDebug($arrMessage);
                }

                if (isset($arrMessage[1]) && strtotime($arrMessage[1]) && sizeof($arrMessage) > 2)
                    $lastIndexWithRealMessageArray = $index;
                else {
                    if ($this->verbose)
                        echo("Insert content of $index to $lastIndexWithRealMessageArray");
                    if ($arrMessage[0] == NULL || empty($arrMessage))
                        $arrTmp[$lastIndexWithRealMessageArray][2] = $arrTmp[$lastIndexWithRealMessageArray][2] . "\n" . " ";
                    else $arrTmp[$lastIndexWithRealMessageArray][2] = $arrTmp[$lastIndexWithRealMessageArray][2] . "\n" . $arrMessage[0];
                    unset($arrTmp[$index]);
                }
            }

            $date = [];
            foreach ($arrTmp as $index => $arrMessage) {
                $date[$index] = strtotime($arrMessage[1]);
            }
            array_multisort($date, SORT_ASC, $arrTmp);
            $arrResult[$folder] = array_values($arrTmp);
        }
        $date = [];
        foreach ($arrResult as $index => $arrMessages) {
            if (sizeof($arrMessages) == 0){
                unset($arrResult[$index]);
                continue;
            }
            $date[$index] = strtotime($arrMessages[sizeof($arrMessages)-1][1]);
        }
        array_multisort($date, SORT_DESC, $arrResult);
        return $arrResult;
    }

    public function downloadAttachments($downloadFolder = "downloads")
    {
        if (!is_dir(SOURCE_FOLDER . DIRECTORY_SEPARATOR . $downloadFolder))
            mkdir(SOURCE_FOLDER . DIRECTORY_SEPARATOR . $downloadFolder);
        foreach ($this->arrMessages as $id => $arrMessage) {
            foreach ($arrMessage as $index => $value) {
                if (!empty($value[3])) {
                    if (strpos($value[3], " ")) {
                        $this->downloadImage(explode(" ", $value[3]));
                    } else {
                        $this->downloadImage(array($value[3]));
                    }
                }
            }

        }
    }

    /**
     * @param bool $verbose
     */
    public function setVerbose(bool $verbose)
    {
        $this->verbose = $verbose;
    }

    /**
     * @return bool
     */
    public function isVerbose(): bool
    {
        return $this->verbose;
    }

    private function downloadImage(array $urls)
    {
        foreach ($urls as $url) {
            $status = "Download from $url";
            $content = $this->file_get_content_curl($url);
            file_put_contents("downloads/" . bin2hex(random_bytes(2)) . ".jpg", $content);
            if ($content == "" || empty($content)) {
                $status = "[FAILED] " . $status;
            } else $status = "[OK] " . $status;
            echo($status);
            @ob_flush();
        }
    }

    private function file_get_content_curl($url)
    {
        // Throw Error if the curl function does'nt exist.
        if (!function_exists('curl_init')) {
            die('CURL is not installed!');
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $output = curl_exec($ch);
        curl_close($ch);
        return $output;
    }

    public function getTableWithChats(): string
    {
        // [ID] | [Name] | [FirstMessage] | [LastMessage] | [TotalMessages]
        $strHTML = "
        <style>
            #table_chatOverview {
              font-family: Arial, Helvetica, sans-serif;
              border-collapse: collapse;
              width: 100%;
            }
            
            #table_chatOverview td, #table_chatOverview th {
              border: 1px solid #ddd;
              padding: 8px;
            }
            
            #table_chatOverview tr:nth-child(even){background-color: #f2f2f2;}
            
            #table_chatOverview tr:hover {background-color: #ddd;}
            
            #table_chatOverview th {
              padding-top: 12px;
              padding-bottom: 12px;
              text-align: left;
              background-color: #4CAF50;
              color: white;
            }
        </style>
        <table id='table_chatOverview'>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>First message</th>
                <th>Last message</th>
                <th>Total messages</th>
                <th>Link</th>
            </tr>";
        foreach ($this->arrMessages as $id => $arrMessage) {
            if ($arrMessage == NULL)
                continue;
            $strHTML .= "
            <tr>
                <td>$id</td>
                <td>" . $this->getNameByID($id) . "</td>
                <td>" . $this->convertTimeToHumanReadable($arrMessage[0][1]) . "</td>
                <td>" . $this->convertTimeToHumanReadable($arrMessage[sizeof($arrMessage) - 1][1]) . "</td>
                <td>" . sizeof($arrMessage) . "</td>
                <td><a href=\"?openChat=$id\" target='_blank'>Open</a></td>
            </tr>
            ";
        }

        $strHTML .= "
        </table>";
        return $strHTML;
    }

    public function getTableWithChatContent(int $id, array $arrIndexToSelect = null): string
    {
        if (!isset($this->arrMessages[$id]) || empty($this->arrMessages[$id])) {
            return "<b>The ID \"$id\" does not exists in this export!</b>";
        }

        // [ID] | [Name] | [Time] | [Message] | [Attachment]
        $strHTML = "
        <h2 style='text-align: center;'>Chat with \"".$this->getNameByID($id)."\"</h2>
        <style>
            #table_chatOverview {
              font-family: Arial, Helvetica, sans-serif;
              border-collapse: collapse;
              width: 100%;
            }
            
            #table_chatOverview td, #table_chatOverview th {
              border: 1px solid #ddd;
              padding: 8px;
            }
            
            #table_chatOverview #selected{background-color: #efe69d;}
            
            #table_chatOverview tr:nth-child(even){background-color: #f2f2f2;}
            
            #table_chatOverview tr:hover {background-color: #ddd;}
            
            #table_chatOverview th {
              padding-top: 12px;
              padding-bottom: 12px;
              text-align: left;
              background-color: #4CAF50;
              color: white;
            }
        </style>
        <table id='table_chatOverview'>
            <tr>
                <th>Index</th>
                <th>ID</th>
                <th>Time</th>
                <th>Message</th>
                <th>Attachment</th>
            </tr>";
        foreach ($this->arrMessages[$id] as $index => $arrMessage) {
            if ($arrMessage == NULL)
                continue;
            $selected = "";
            if ($arrIndexToSelect != null && in_array($index, $arrIndexToSelect)){
                $selected = "id='selected'";
            }

            $strHTML .= "
            <tr $selected>
                <td>$index</td>
                <td>$arrMessage[0]</td>
                <td>" . (empty($arrMessage[1]) ? "" : $this->convertTimeToHumanReadable($arrMessage[1])) . "</td>
                <td>" . (empty($arrMessage[2]) ? "" : str_replace("\n", "<br>", $arrMessage[2])) . "</td>
                <td>" . (empty($arrMessage[3]) ? "" : $this->getHrefLinks($arrMessage[3])) . "</td>
            </tr>
            ";
        }
        if ($this->verbose)
            $this->echoDebug($this->arrMessages[$id]);
        $strHTML .= "
        </table>";
        return $strHTML;
    }

    public function getTableWithSearchResults(array $searchResults, string $searchTerm): string
    {
        // [ID] | [Name] | [Time] | [Message] | [Attachment] | [Link]
        $strHTML = "
        <style>
            #table_chatOverview {
              font-family: Arial, Helvetica, sans-serif;
              border-collapse: collapse;
              width: 100%;
            }
            
            #table_chatOverview td, #table_chatOverview th {
              border: 1px solid #ddd;
              padding: 8px;
            }
            
            #table_chatOverview tr:nth-child(even){background-color: #f2f2f2;}
            
            #table_chatOverview tr:hover {background-color: #ddd;}
            
            #table_chatOverview th {
              padding-top: 12px;
              padding-bottom: 12px;
              text-align: left;
              background-color: #4CAF50;
              color: white;
            }
        </style>
        <table id='table_chatOverview'>
            <tr>
                <th>ID</th>
                <th>Time</th>
                <th>Message</th>
                <th>Attachment</th>
                <th>Link</th>
            </tr>";
        foreach ($searchResults as $id => $arrMessages) {
            foreach ($arrMessages as $index => $arrMessage) {
                if ($arrMessage == NULL)
                    continue;

                $strHTML .= "
            <tr>
                <td>$arrMessage[0]</td>
                <td>" . (empty($arrMessage[1]) ? "" : $this->convertTimeToHumanReadable($arrMessage[1])) . "</td>
                <td>" . (empty($arrMessage[2]) ? "" : str_replace("\n", "<br>", $this->highlightMessage($arrMessage[2], $searchTerm))) . "</td>
                <td>" . (empty($arrMessage[3]) ? "" : $this->getHrefLinks($arrMessage[3])) . "</td>
                <td><a href=' ?openChat=$id&select=$index' target='_blank'>Open</a></td>
            </tr>
            ";
            }
        }
        if ($this->verbose)
            $this->echoDebug($this->arrMessages[$id]);
        $strHTML .= "
        </table>";
        return $strHTML;
    }

    public function searchForMessage(string $content): array
    {
        $arrResult = [];
        foreach ($this->arrMessages as $id => $arrMessages) {
            foreach ($arrMessages as $index => $arrMessage) {
                if (preg_match("(.*$content.*)", $arrMessage[2])) {
                    if (!isset($arrResult[$id]))
                        $arrResult[$id] = [];
                    $arrResult[$id][$index] = $arrMessage;
                }
            }
        }
        return $arrResult;
    }

    private function convertTimeToHumanReadable(string $time): string
    {
        // 2019-11-23 17:26:40.562000+00:00
        return date("d.m.Y h:i:s", strtotime($time));
    }

    private function getHrefLinks($raw)
    {
        if (empty($raw))
            return "";
        $strHTML = "";
        if (strpos($raw, " ")) {
            $intCount = 1;
            foreach (explode($raw, " ") as $index => $item) {
                $strHTML .= "<a href='$item' target='_blank'>Attachment #$intCount</a>";
            }
        } else $strHTML .="<a href='$raw' target='_blank'>Attachment #1</a>";
        return $strHTML;
    }

    private function getNameByID($id)
    {
        if ($this->arrAccountData["id"] == $id)
            return $this->arrAccountData["username"];
        if (!empty($this->arrIDMapping[$id]))
            return $this->arrIDMapping[$id];
        return "N/A";
    }

    private function echoDebug($object)
    {
        echo("<pre>" . var_export($object, true) . "</pre>");
    }

    private function highlightMessage(string $message, string $toHighlight) : string
    {
        return preg_replace("/\w*?$toHighlight\w*/i", "<mark>$0</mark>", $message);
    }
}