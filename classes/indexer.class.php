<?php
$_SESSION['base'] = '.';
require('./indexer/IndexerService.php');
?>

<?php
session_start();

if (isset($_GET['function'])) {
    $indexerService = new IndexerService();

    $i = new indexer();
    require_once "mysql.class.php";
    $mysql = new MySQL();


    if ($_GET['function'] == 'init') {
        $indexerService->init();
    }

    if ($_GET['function'] == 'indexIt') {

        $conn = Database::getInstance()->getConnection();

        $result = mysqli_query($conn, "select count(*) as c from `crawler_tree`");
        $row = mysqli_fetch_array($result);
        $no_of_links = $row['c'];
        //echo $no_of_links;

        $result = mysqli_query($conn, "select id,url from `crawler` where ftch=1");
        if ($row = mysqli_fetch_array($result)) {
            $id = $row['id'];
            $url = $row['url'];

            $xml = simplexml_load_file("indexData/" . $id . ".xml");
            $title = preg_replace('/^( )*$/', "No title", $xml->title);
            $linksCount = (int)$xml->out->attributes() + (int)$xml->links->attributes();

            $output = mysqli_fetch_array(mysqli_query($conn, "select count(*) as count from crawler_tree where child_id=$id"));
            $inLinks = $output['count'];


            $outXML = simplexml_load_file("../data/links/" . $_SESSION['domainID'] . ".xml");

            if (!$link = $outXML->XPath("/domain/link[url = '$url']")) {

                $link = $outXML->addChild('link');

                $_SESSION['linkID'] = count($outXML);
                $link->addAttribute('id', count($outXML));
                $link->url = $url;
                $link->title = $title;
                $link->addAttribute('PageRank', ($inLinks / $no_of_links) * 100);
            } else {
                $att = 'id';
                $_SESSION['linkID'] = (int)$link[0]->attributes()->$att;

                $link[0]->title = $title;
                $att = 'PageRank';
                $link[0]->attributes()->$att = ($inLinks / $no_of_links) * 100;
            }
            $outXML->asXML("../data/links/" . $_SESSION['domainID'] . ".xml");


            mysqli_query($conn, "UPDATE crawler SET ftch=0 WHERE id='$id'");


            $i->indexIt($xml, $_SESSION['domainID'], $_SESSION['linkID']);
            echo "Indexed : $url<br><hr>";
        } else die('die-error: Completed<br>');

    }
}

?>

<?php

class indexer
{


    function indexIt($xml, $domainId, $linkId)
    {


        $keywords = simplexml_load_file("../data/keywords.xml");
        foreach ($xml->words->children() as $word) {

            if (!$keyword = $keywords->XPath("/keywords/keyword[. = '$word']")) {
                $keyword = $keywords->addChild('keyword', (string)$word);
                $keywordID = count($keywords);
                $keyword->addAttribute('id', $keywordID);
            } else {
                $att = 'id';
                $keywordID = $keyword[0]->attributes()->$att;
            }


            $occur = $word->attributes();
            $word = (string)$word;
            $this->insert($keywordID, $word[0], $domainId, $linkId, $occur[0]);

        }


        $keywords->asXML("../data/keywords.xml");
    }


    function insert($keywordID, $char, $domainId, $linkId, $occur)
    {


        if (preg_match('/^[a-z0-9]$/i', $char)) $path = "../data/database/" . $char . ".xml";
        else $path = "../data/database/others.xml";

        $xml = simplexml_load_file($path);

        if (!$keyword = $xml->XPath("/data/keyword[@id = '$keywordID']")) {

            $keyword = $xml->addChild('keyword');
            $keyword->addAttribute('id', $keywordID);
        }


        if (!$link = $keyword[0]->XPath("/link[@id = '$linkId' and @domain = '$domainId']")) {
            $link = $keyword[0]->addChild('link');
            $link->addAttribute('id', $linkId);
            $link->addAttribute('domain', $domainId);
            $link->addAttribute('occur', $occur);
        } else {
            $att = 'occur';
            $link[0]->attributes()->$att = $occur;
        }


        $xml->asXML($path);
    }



}


?>