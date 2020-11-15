<?php
require_once "DiscordExportAnalyzer.php";

if (!is_file("package")){
    if (is_file("package.zip")){

    } else die("The Discord-data-export package must be on the same directory as this script, please ");
}

$da = new DiscordExportAnalyzer("package");


if (isset($_GET["search"])){
    $searchResults = $da->searchForMessage(html_entity_decode($_GET["search"]));
    echo $da->getTableWithSearchResults($searchResults);
}

if (isset($_GET["openChat"])){
    if(isset($_GET["select"]))
        echo $da->getTableWithChatContent($_GET["openChat"], explode(",",$_GET["select"]));
    else echo $da->getTableWithChatContent($_GET["openChat"]);
} else {

//    echo $da->getTableWithChats();
}



