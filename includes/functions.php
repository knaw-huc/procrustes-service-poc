<?php
$db = new db();

function get_dummy()
{
    global $db;

    send_json($db->dummy());
}

function detail($base)
{
    $data = json_decode(base64_decode($base), true);
    $dataset = $data["dataset"];
    $uri = $data["uri"];
    $json = file_get_contents(MODEL_DIR . $dataset. ".json");
    $struc = json_decode($json, true);
    $collection = $struc["prefix"] . $struc["collection_prefix"] . $struc["entity"]["collection"];
    $query = "{\"query\": {\"match\": {\"uri.keyword\": \"$uri\"}}}";
    $result = elastic($query, $struc["entity"]["title"]);
    $item = $result["hits"]["hits"][0]["_source"];
    send_json(structureDetailData($item, $struc["entity"]["notions"], $collection, $struc["dataset_id"]));
}

function structureDetailData($item, $notions, $collection, $searchIndex) {
    $retArray = array();
    foreach ($notions as $notion) {
        $buffer = array();
        $buffer["key"] = $notion["title"];
        $buffer["value"] = fillFieldValue($item[$notion["name"]]);
        $retArray["details"][] = $buffer;
    }
    $retArray["uri"] = $item["uri"];
    $retArray["collection"] = $collection;
    if ($searchIndex == "u33707283d426f900d4d33707283d426f900d4d0d__repsessions") {
        $retArray["see_also"] = getLinkedDelegates($item["spans"]);
    } else {
        $retArray["see_also"] = getLinkedItems($item["id"], $searchIndex);
    }

    return $retArray;
}

function getLinkedDelegates($list) {
    $retArray = array();
    $ids = array();
    foreach ($list as $el) {
       $ids[] = $el["delegate_id"];
    }
    $ids = array_unique($ids, SORT_NUMERIC);
    foreach ($ids as $id) {
        $buffer = getLinkedItems($id, "dummy");
        foreach ($buffer as $delegate) {
            $retArray[] = $delegate;
        }
    }
    return $retArray;
}

function getLinkedItems($id, $collection) {
    $retArray = array();
    $collections = array(
        "u33707283d426f900d4d33707283d426f900d4d0d__abbreviated_delegates",
        "u33707283d426f900d4d33707283d426f900d4d0d__delegates"
    );
    foreach ($collections as $coll) {
        if ($coll !== $collection) {
            $buffer = getLinkedItem($id, $coll);
            if ($buffer["index"] !== "") {
                $retArray[] = $buffer;
            }
        }
    }
    return $retArray;
}

function getLinkedItem($id, $coll) {
    $json = file_get_contents(MODEL_DIR . $coll. ".json");
    $struc = json_decode($json, true);
    $query = "{\"query\": {\"bool\": {\"must\": [{\"match\": {\"id\": $id }}]}}}";
    $result = elastic($query, $struc["entity"]["title"]);
    if ($result["hits"]["total"]["value"] === 0) {
        return array("head" => "", "body" => "", "uri" => "", "index" => "");
    } else {
        $entity = $result["hits"]["hits"][0]["_source"];
        $retArr = processResultItem($entity, $struc);
        $retArr["index"] = $struc["dataset_id"];
        return $retArr;
     }
}

function fillFieldValue($str) {
    $retArray = array();
    if (is_array($str)) {
        foreach ($str as $el) {
            $retArray[] = $el["delegate_name"];
        }
        sort($retArray);
        $retArray = array_unique($retArray);
        return implode(", ", $retArray);
    } else {
        if (is_null($str) || strlen($str) == 0) {
            return "-";
        } else {
            return ($str);
        }
    }

}

function elastic($json_struc, $collection)
{
    //error_log(ELASTIC_HOST . $collection . "/_search?");
    $options = array('Content-type: application/json', 'Content-Length: ' . strlen($json_struc));
    $ch = curl_init(ELASTIC_HOST . $collection . "/_search?");
    curl_setopt($ch, CURLOPT_HTTPHEADER, $options);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $json_struc);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

function download($codedStruc)
{
    global $db;

    $queryArray = json_decode(base64_decode($codedStruc), true);
    $json_struc = parse_codedStruc($queryArray, true);
    $result = elastic($json_struc);
    $ids = array();
    foreach ($result["hits"]["hits"] as $manuscript) {
        $ids[] = "'" . $manuscript["_source"]["id"] . "'";
    }
    $downloadData = $db->getDownloadDetails(implode(", ", $ids));
    $row = $downloadData[0];
    header("Content-Disposition: attachment; filename=isidore_results.csv");
    header("Content-Type: text/csv");
    $fp = fopen('php://output', 'w');
    fputcsv($fp, array_keys($row), "\t");
    foreach ($downloadData as $data) {
        fputcsv($fp, $data, "\t", '"');
    }
}

function browse($ds_id, $struc)
{
    $queryStruc = json_decode(base64_decode($struc), true);
    $json = file_get_contents(MODEL_DIR . $ds_id . ".json");
    $struc = json_decode($json, true);
    $searchText = trim($queryStruc["text"]);
    if (strlen($searchText)) {
        $query = createSearchQuery($struc, $queryStruc["page"], $searchText);
    } else {
        $query = createQuery($struc, $queryStruc["page"]);
    }
    $collection = $struc["entity"]["title"];
    $result = elastic($query, $collection);
    send_json(unifyResult($result, $struc, $queryStruc["page"]));
}

function unifyResult($result, $struc, $page)
{
    $retArray = array();
    $retArray["total_hits"] = $result["hits"]["total"]["value"];
    $retArray["page"] = $page;
    $retArray["total_pages"] = ceil($result["hits"]["total"]["value"] / BROWSE_PAGE_LENGTH);
    $retArray["dataset_name"] = $struc["entity"]["title"];
    $retArray["dataset_id"] = $struc["dataset_id"];
    $retArray["items"] = array();
    foreach ($result["hits"]["hits"] as $item) {
        $retArray["items"][] = processResultItem($item["_source"], $struc);
    }
    return $retArray;
}

function processResultItem($item, $struc)
{
    $result_fields = explode(" ", $struc["search"]["result_fields"]);
    $result_label = $struc["search"]["result_label"];
    $labelArray = explode("#", $result_label);
    $preHead = $labelArray[0];
    if (isset($labelArray[1])) {
        $preBody = $labelArray[1];
    } else {
        $preBody = "";
    }
    $head = processItemHead($item, $preHead, $struc["search"]);
    $body = processItemBody($item, $preBody);
    return array("head" => $head, "body" => $body, "uri" => $item["uri"]);
}

function processItemHead($item, $list, $searchData) {
    $retStr = "";
    $parts = explode(";", $list);
    foreach ($parts as $part) {
        if (array_key_exists($part, $item)) {
            if (isset($item[$part])) {
                $retStr .= $item[$part];
            } else {
                $retStr .= "??";
            }
        } else {
            $retStr .= $part;
        }
    }
    return $retStr;
}

function processItemBody($item, $list) {
    $retArray = array();
    if (strlen($list)) {
        $fields = explode(";", $list);
        foreach ($fields as $field) {
            $retArray[] = $item[$field];
        }
    }
    return $retArray;
}




function createQuery($struc, $page)
{
    $from = ($page-1) * BROWSE_PAGE_LENGTH;
    $order = $struc["search"]["order"];
    $fields = $struc["search"]["result_fields"];
    $page_length = BROWSE_PAGE_LENGTH;
    $queryFields = getFields($fields);
    $retValue = "{ \"query\": {\"match_all\": {}}, \"size\": $page_length, \"from\": $from, \"_source\": [\"id\", $queryFields, \"uri\"],\"sort\": [{ \"$order.keyword\": {\"order\":\"asc\"}}]}";
    return $retValue;
}

function createSearchQuery($struc, $page, $text) {
    $from = ($page-1) * BROWSE_PAGE_LENGTH;
    $order = $struc["search"]["order"];
    $fields = $struc["search"]["result_fields"];
    $page_length = BROWSE_PAGE_LENGTH;
    $queryFields = getFields($fields);
    $retValue = "{ \"query\": {\"multi_match\": {\"query\": \"$text\", \"fields\": []}}, \"size\": $page_length, \"from\": $from, \"_source\": [\"id\", $queryFields, \"uri\"],\"sort\": [{ \"$order.keyword\": {\"order\":\"asc\"}}]}";
    return $retValue;
}

function getFields($fields)
{
    $retArray = array();
    $values = explode(" ", $fields);
    foreach ($values as $value) {
        $retArray[] = "\"$value\"";
    }
    return implode(", ", $retArray);
}

function search($codedStruc)
{
    $queryArray = json_decode(base64_decode($codedStruc), true);
    $json_struc = parse_codedStruc($queryArray);
    $send_back = array();
    $result = elastic($json_struc);
    $send_back["amount"] = $result["hits"]["total"]["value"];
    $send_back["pages"] = ceil($send_back["amount"] / $queryArray["page_length"]);
    $send_back["manuscripts"] = array();
    foreach ($result["hits"]["hits"] as $manuscript) {
        $send_back["manuscripts"][] = $manuscript["_source"];
    }
    send_json($send_back);
}

function parse_codedStruc($queryArray, $download = false)
{
    $page_length = $queryArray["page_length"];
    $from = ($queryArray["page"] - 1) * $queryArray["page_length"];
    $sortOrder = $queryArray["sortorder"];
    if ($queryArray["searchvalues"] == "none") {
        $json_struc = "{ \"query\": {\"match_all\": {}}, \"size\": $page_length, \"from\": $from, \"_source\": [\"id\", \"shelfmark\", \"bischoff\", \"cla\",\"scaled_dates.date\", \"physical_state\",  \"absolute_places.place_absolute\", \"absolute_places.latitude\", \"absolute_places.longitude\", \"library.place_name\", \"library.latitude\", \"library.longitude\", \"certainty\", \"no_of_folia\", \"page_height_min\", \"page_width_min\", \"designed_as\" ,\"material_type\", \"books_latin\", \"additional_content_scaled\", \"image\"]}";
    } else {
        $json_struc = buildQuery($queryArray, $from, $page_length, $sortOrder, $download);
    }
    return $json_struc;
}

function buildQuery($queryArray, $from, $page_length, $sortOrder, $download)
{
    $terms = array();

    foreach ($queryArray["searchvalues"] as $item) {
        if (strpos($item["field"], '.')) {
            $fieldArray = explode(".", $item["field"]);
            $terms[] = nestedTemplate($fieldArray, makeItems($item["values"]));
        } else {
            $terms[] = matchTemplate($item["field"], makeItems($item["values"]));
        }

    }

    return queryTemplate(implode(",", $terms), $from, $page_length, $sortOrder, $download);
}

function matchTemplate($term, $value)
{
    switch ($term) {
        case "FREE_TEXT":
            return "{\"multi_match\": {\"query\": $value}}";
        case "BOOK":
            return bookValues($value);
        default:
            return "{\"terms\": {\"$term.raw\": [$value]}}";
    }
}

function nestedTemplate($fieldArray, $value)
{
    $path = $fieldArray[0];
    $field = implode(".", $fieldArray);
    return "{\"nested\": {\"path\": \"$path\",\"query\": {\"bool\": {\"must\": [{\"terms\": {\"$field.raw\": [$value]}}]}}}}";
}

function queryTemplate($terms, $from, $page_length, $sortOrder, $download)
{
    if ($download) {
        return "{ \"query\": { \"bool\": { \"must\": [ $terms ] } }, \"size\": 500, \"from\": 0, \"_source\": [\"id\"]}";
    } else {
        return "{ \"query\": { \"bool\": { \"must\": [ $terms ] } }, \"size\": $page_length, \"from\": $from, \"_source\": [\"id\", \"shelfmark\", \"bischoff\", \"cla\",\"scaled_dates.date\", \"physical_state\",  \"absolute_places.place_absolute\", \"absolute_places.latitude\", \"absolute_places.longitude\", \"library.place_name\", \"library.latitude\", \"library.longitude\", \"certainty\", \"no_of_folia\", \"page_height_min\", \"page_width_min\", \"designed_as\" ,\"material_type\", \"books_latin\", \"additional_content_scaled\", \"image\"]}";
    }

}

function bookValues($book)
{
    $book = str_replace("\"", "", $book);
    $bookSplit = explode(":", $book);
    $base = romanToNumeric($bookSplit[0]);
    $range = explode("-", $bookSplit[1]);
    $from = $base + $range[0];
    $to = $base + $range[1];
    return "{\"range\": {\"books.details.section\": {\"from\": $from, \"to\": $to}}}";
}


function makeItems($termArray)
{
    $retArray = array();

    foreach ($termArray as $term) {
        $retArray[] = "\"" . $term . "\"";
    }
    return implode(", ", $retArray);
}

function get_filter_facets($searchStruc)
{
    $queryArray = json_decode(base64_decode($searchStruc), true);
    $subQuery = parseQueryFields($queryArray);
    $values = array();
    $values["annotations"] = get_filter_facet_amount("annotations", "yes", $subQuery);
    $values["digitized"] = get_filter_facet_amount("digitized", "yes", $subQuery);
    $values["excluded"] = get_filter_facet_amount("excluded", "yes", $subQuery);
    $values["part"] = get_filter_facet_amount("part", "yes", $subQuery);
    send_json($values);
}

function parseQueryFields($queryArray)
{
    if ($queryArray["searchvalues"] == "none") {
        return "none";
    }

    $terms = array();

    foreach ($queryArray["searchvalues"] as $item) {
        if (strpos($item["field"], '.')) {
            $fieldArray = explode(".", $item["field"]);
            $terms[] = nestedTemplate($fieldArray, makeItems($item["values"]));
        } else {
            $terms[] = matchTemplate($item["field"], makeItems($item["values"]));
        }

    }

    return implode(",", $terms);
}

function get_filter_facet_amount($field, $value, $subQuery)
{
    $retValue = 0;
    if ($subQuery == "none") {
        $json_struc = "{\"size\": 0, \"aggs\": {\"names\": {\"terms\": {\"field\": \"$field.raw\", \"size\": 10}}}}";
    } else {
        $json_struc = "{ \"query\": { \"bool\": { \"must\": [ $subQuery ] } }, \"size\": 0, \"aggs\": {\"names\": {\"terms\": {\"field\": \"$field.raw\", \"size\": 10}}}}";
    }

    $result = elastic($json_struc);
    $buckets = $result["aggregations"]["names"]["buckets"];
    foreach ($buckets as $bucket) {
        if ($bucket["key"] == $value) {
            $retValue = $bucket["doc_count"];
        }
    }
    return $retValue;
}


function get_facets($field, $searchStruc, $filter, $type)
{
    if ($type == 'long') {
        $amount = 400;
    } else {
        $amount = 10;
    }
    $queryArray = json_decode(base64_decode($searchStruc), true);
    $subQuery = parseQueryFields($queryArray);
    if ($subQuery == "none") {
        $json_struc = "{\"query\": {\"regexp\": {\"$field\": {\"value\": \"$filter.*\"}}},\"aggs\": {\"names\" : {\"terms\" : { \"field\" : \"$field.raw\",  \"size\" : $amount }}}}";
    } else {
        $json_struc = "{\"query\":  {\"bool\": { \"must\": [ $subQuery , {\"regexp\": {\"$field\": {\"value\": \"$filter.*\"}}}] }},\"aggs\": {\"names\" : {\"terms\" : { \"field\" : \"$field.raw\",  \"size\" : $amount }}}}";
    }

    $result = elastic($json_struc);
    send_json(array("buckets" => $result["aggregations"]["names"]["buckets"]));
}

function get_nested_facets($field, $searchStruc, $type, $filter = "")
{
    switch ($type) {
        case "long":
            $amount = 400;
            break;
        case "normal":
            $amount = 100;
            break;
        default:
            $amount = 10;
            break;
    }
    $queryArray = json_decode(base64_decode($searchStruc), true);
    $subQuery = parseQueryFields($queryArray);

    $field_elements = explode(".", $field);
    $path = $field_elements[0];
    if ($subQuery == "none") {
        $json_struc = "{\"size\": 0,\"aggs\": {\"nested_terms\": {\"nested\": {\"path\": \"$path\"},\"aggs\": {\"filter\": {\"filter\": {\"regexp\": {\"$field\": \"$filter.*\"}},\"aggs\": {\"names\": {\"terms\": {\"field\": \"$field.raw\",\"size\": $amount}}}}}}}}";
    } else {
        $json_struc = "{\"query\": { \"bool\": { \"must\": [ $subQuery ] } }, \"size\": 0, \"aggs\": {\"nested_terms\": {\"nested\": {\"path\": \"$path\"},\"aggs\": {\"filter\": {\"filter\": {\"regexp\": {\"$field\": \"$filter.*\"}},\"aggs\": {\"names\": {\"terms\": {\"field\": \"$field.raw\",\"size\": $amount}}}}}}}}";
    }
    //error_log($json_struc);
    $result = elastic($json_struc);
    send_json(array("buckets" => $result["aggregations"]["nested_terms"]["filter"]["names"]["buckets"]));
}

function get_initial_facets($field, $searchStruc, $type)
{
    switch ($type) {
        case "long":
            $amount = 400;
            break;
        case "normal":
            $amount = 100;
            break;
        default:
            $amount = 10;
            break;
    }

    $queryArray = json_decode(base64_decode($searchStruc), true);
    $subQuery = parseQueryFields($queryArray);
    if ($subQuery == "none") {
        $json_struc = "{\"size\": 0,\"aggs\" : {\"names\" : {\"terms\" : { \"field\" : \"$field.raw\",  \"size\" : $amount }}}}";
    } else {
        $json_struc = "{\"query\": { \"bool\": { \"must\": [ $subQuery ] } }, \"size\": 0, \"aggs\" : {\"names\" : {\"terms\" : { \"field\" : \"$field.raw\",  \"size\" : $amount }}}}";
    }
    $result = elastic($json_struc);
    echo send_json(array("buckets" => $result["aggregations"]["names"]["buckets"]));
}

function get_metadata($dataset_id)
{
    $tq = new Timquery();
    $json = "{ dataSets { $dataset_id {metadata {published title {value} description {value} imageUrl {value} owner {name {value} email {value}} contact {name {value} email {value}} provenanceInfo {title {value} body {value}} license {uri}}}}}";

    $result = $tq->get_graphql_data($json);
    send_json($result["data"]["dataSets"][$dataset_id]["metadata"]);
}


function throw_error($error = "Bad request")
{
    $response = array("error" => $error);
    send_json($response);
}

function send_json($message_array)
{
    header('Content-Type: application/json; charset=UTF-8');
    header('Access-Control-Allow-Origin: *');
    echo json_encode($message_array);
}

function send_elastic($json)
{
    header('Content-Type: application/json; charset=UTF-8');
    header('Access-Control-Allow-Origin: *');
    echo $json;
}