<?php

require_once("../conf/bootstrap.php");

//читаем данные и HTTP-запроса, строим из них XML по схеме
$hreq = new HTTP_Request2Xml("schemas/TestApp/DocumentListRequest.xsd");
$req=new TestApp_DocumentListRequest();
if (!$hreq->isEmpty()) {
	$hreq->validate();
        $req->fromXmlStr($hreq->getAsXML());
}

// формируем xml-ответ
$xw = new XMLWriter();
$xw->openMemory();
$xw->setIndent(TRUE);
$xw->startDocument("1.0", "UTF-8");
$xw->writePi("xml-stylesheet", "type=\"text/xsl\" href=\"stylesheets/TestApp/DocumentList.xsl\"");
$xw->startElementNS(NULL, "DocumentListResponse", "urn:ru:ilb:meta:TestApp:DocumentListResponse");
$req->toXmlWriter($xw);
// Если есть входные данные, проведем вычисления и выдадим ответ
if (!$hreq->isEmpty()) {
    
        //Подключение к базе данных.
	$pdo=new PDO("mysql:host=localhost;dbname=testapp","testapp","1qazxsw2",array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
        //$pdo=new PDO("mysql:host=127.12.155.130;dbname=testapp;charset=UTF-8","testapp","1qazxsw2",array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
        
        //prior to PHP 5.3.6, the charset option was ignored. If you're running an older version of PHP, you must do it like this:
	$pdo->exec("set names utf8");
        $query = "SELECT * FROM document"
                . " WHERE docDate BETWEEN :dateStart AND :dateEnd"
                . " AND displayName like concat('%',:displayName,'%')";
	$sth=$pdo->prepare($query);
	$sth->execute(array(
            ":dateStart"=>$req->dateStart,
            ":dateEnd"=>$req->dateEnd,
            ":displayName"=>$req->displayName,
        ));
	while($row=$sth->fetch(PDO::FETCH_ASSOC)) {
		$doc = new TestApp_Document();
		$doc->fromArray($row);
		$doc->toXmlWriter($xw);                
	}
}
$xw->endElement();
$xw->endDocument();
//Вывод ответа клиенту

if(!$hreq->isEmpty() && $req->outputFormat=='pdf') {
    xmltopdf($xw->flush());
} else {
    header("Content-Type: text/xml");
    echo $xw->flush();
}
/**
 * Функция для формирования pdf
 * @param type $xml
 * @throws Exception
 */
function xmltopdf($xml)
{       
    //Transform source xml to fo
    $xmldom = new DOMDocument();
    $xmldom->loadXML($xml);
    $xsldom = new DomDocument();
    $xsldom->load("stylesheets/TestApp/to_pdf.xsl");
    $proc = new XSLTProcessor();
    $proc->importStyleSheet($xsldom);
    $res = $proc->transformToXML($xmldom);
    
    //Transform fo to pdf
    //fop servlet url
    $url = "http://tomcat-bystrobank.rhcloud.com/fopservlet/fopservlet";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    //specify mime-type of source data
    curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: application/xml"));
    //post contents - fo souce
    curl_setopt($ch, CURLOPT_POSTFIELDS, $res);    
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // возврат результата в переменную
        
    $res = curl_exec($ch);    
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    //check http response code
    if ($code != 200) {
        throw new Exception($res . PHP_EOL . $url . " " . curl_error($ch), 450);        
    }        
    curl_close($ch);
        
    $attachmentName = "test.pdf";
    $headers = array(
        "Content-Type: application/pdf",
        "Content-Disposition: inline; filename*=UTF-8''" . $attachmentName
    );    
    foreach ($headers as $h) {
        header($h);
    }
    echo $res;
}