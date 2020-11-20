<html>
<head>
    <title>Discord Export Chat Viewer</title>
</head>
<body>
<form action="index.php" method="get">
    <input name="search" placeholder="Search...">
    <button type="submit">Search</button>
</form>
</body>
</html>

<?php
require_once "DiscordExportAnalyzer.php";

if (!is_dir(__DIR__ . DIRECTORY_SEPARATOR."package")) {
    if (is_file(__DIR__.DIRECTORY_SEPARATOR."package.zip")) {
        if (!extension_loaded("zip")){
            die("package.zip detected but the extension \"zip\" is not loaded! exiting..");
        }
        $zip = new ZipArchive();
        $zip->open(__DIR__.DIRECTORY_SEPARATOR."package.zip");
        mkdir(__DIR__ . DIRECTORY_SEPARATOR."package");
        $zip->extractTo(__DIR__ . DIRECTORY_SEPARATOR."package");
    } else die("The Discord-data-export package must be on the same directory as this script, also it must be named \"package\"!");
}
$da = new DiscordExportAnalyzer(__DIR__ . DIRECTORY_SEPARATOR."package");


if (isset($_GET["search"])) {
    $searchResults = $da->searchForMessage(html_entity_decode($_GET["search"]));
    echo $da->getTableWithSearchResults($searchResults, html_entity_decode($_GET["search"]));
    return;
} else if (isset($_GET["openChat"])) {
    if (isset($_GET["select"]))
        echo $da->getTableWithChatContent($_GET["openChat"], explode(",", $_GET["select"]));
    else echo $da->getTableWithChatContent($_GET["openChat"]);
    return;
} else echo $da->getTableWithChats();
?>



