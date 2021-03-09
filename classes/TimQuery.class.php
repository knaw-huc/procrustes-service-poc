<?php

if (!defined('BASE_URL'))
    exit('No direct script access allowed');

class Timquery
{

    function json2array($json)
    {
        return json_decode($json, 'JSON_OBJECT_AS_ARRAY');
    }

    /*function setSimplePerson($uri, $name)
    {
        $json_struc = array();
        $json_struc["query"] = 'mutation Edit ($uri:String! $entity:u33707283d426f900d4d33707283d426f900d4d0d__rob_schema_PersonInput! ) {dataSets {u33707283d426f900d4d33707283d426f900d4d0d__rob {schema_Person {edit(uri: $uri entity: $entity) {uri schema_familyName {value}}}}}}';
        //$json_struc["variables"] = "{\"uri\": \"$uri\",\"entity\": {\"replacements\": {\"schema_name\": {\"type\": \"xsd_string\", \"value\": \"$name\"}}}}";
        $json_struc["variables"] = array("uri" => $uri, "entity" => array("replacements" => array("schema_familyName" => array("type" => "xsd_string", "value" => $name))));
        return json_encode($json_struc);
    }*/

    function getSchema($type)
    {
        return "{ __type(name: \"$type\") {name fields {name type {name kind ofType {name kind}}}}}";
    }

    function getInputFields($type)
    {
        return "query inputFields {__type(name: \"$type\") {name inputFields {name type {name}}}}";
    }

    function getValueType($name)
    {
        if (is_null($name)) {
            return "xsd_string";
        } else {
            return $this->getEnumValue($name);
        }
    }

    private function getEnumValue($name)
    {
        $json = "query enumValues { __type(name: \"{$name}Enum\") {enumValues {name}}}";
        $enumVals = $this->get_graphql_data($json);
        return $enumVals["data"]["__type"]["enumValues"][0]["name"];
    }

    function getTypeAndValue($dataset, $collection, $field, $uri)
    {
        $query = "query typeAndValue {  dataSets { $dataset { $collection (uri: \"{$uri}\") { $field {type value}}}}}";
        $result = $this->get_graphql_data($query);
        $retArray = array();
        $retArray["type"] = $result["data"]["dataSets"][$dataset][$collection][$field]["type"];
        $retArray["value"] = $result["data"]["dataSets"][$dataset][$collection][$field]["value"];
        return $retArray;
    }

    function dropDataSet($id)
    {
        $json_struc = array();
        $json_struc["query"] = 'mutation deleteDataSet($dataSet: String!) {deleteDataSet(dataSetId: $dataSet) {dataSetId}}';
        $json_struc["variables"] = array("dataSet" => $id);
        $result = $this->set_graphql_data(json_encode($json_struc));
    }

    function getAdressedSchema($type)
    {
        $json = $this->getSchema($type);
        $schema = $this->get_graphql_data($json);
        return $this->makeSchemaAdressable($schema);
    }

    function setPlace($uri, $field, $value, $type)
    {
        $json_struc = array();
        $pre_query = 'query place { dataSets { u33707283d426f900d4d33707283d426f900d4d0d__hpp6demo { clusius_Places(uri: "' . $uri . '") { uri ' . $field . ' { value } } } } }';
        $result = $this->get_graphql_data($pre_query);
        $json_struc["query"] = 'mutation place($uri: String!, $entity: u33707283d426f900d4d33707283d426f900d4d0d__hpp6demo_clusius_PlacesInput!) { dataSets { u33707283d426f900d4d33707283d426f900d4d0d__hpp6demo { clusius_Places { edit(uri: $uri, entity: $entity) { uri ' . $field . ' { value } } } } }}';
        $json_struc["variables"] = array("uri" => $uri, "entity" => array("replacements" => array($field => array("type" => $type, "value" => $value))));
        $result = $this->set_graphql_data(json_encode($json_struc));
    }

    function getPlace($uri)
    {
        $json = "query place { dataSets { u33707283d426f900d4d33707283d426f900d4d0d__hpp6demo { clusius_Places(uri: \"$uri\") { tim_name { value } tim_country { value } tim_latitude { value } tim_longitude { value } tim_remarks{ value } skos_altLabelList { items { value } } } } } }";
        return $this->get_graphql_data($json);
    }

    function makeSchemaAdressable($schema, $fieldType = "fields")
    {
        $retArray = array();
        $obj = $schema["data"]["__type"][$fieldType];
        foreach ($obj as $value) {
            $retArray[$value["name"]] = $value["type"];
        }
        return $retArray;
    }

/*    function getPossibleTitleFields($type) {
        $json = "query fields {__type(name: \"${type}CreationsInput\") { inputFields { name type {name}}}}";
        //die($json);
        $fields = $this->get_graphql_data($json);
        return $fields;
    }*/

    function getObjectFields($objectName)
    {
        $json = "query GetObjectFields { __type(name: \"$objectName\") { name kind fields { name type { name kind ofType { name kind} interfaces {name}}}}}";
        return $this->get_graphql_data($json);
    }

    function getDataSetTitle($dataset)
    {
        $json = "{dataSets { " . $dataset . " {metadata {dataSetName title {value}}}}}";
        $result = $this->get_graphql_data($json);

        if (!is_null($result["data"]["dataSets"][$dataset]["metadata"]["title"])) {
            return $result["data"]["dataSets"][$dataset]["metadata"]["title"]["value"];
        } else {
            return $result["data"]["dataSets"][$dataset]["metadata"]["dataSetName"];
        }
    }

    function whoAmI()
    {
        $json = "query whoAmI {aboutMe {id name dataSetMetadataList(ownOnly: false, permission: READ) {dataSetId userPermissions}}}";
        $result = $this->get_graphql_data($json);
        return $this->makeRightsArray($result);
    }

    private function makeRightsArray($user)
    {
        $retArray = array();
        if (isset($user["data"]["aboutMe"]["id"])) {
            $retArray["id"] = $user["data"]["aboutMe"]["id"];
            $retArray["name"] = $user["data"]["aboutMe"]["name"];
            $retArray["rights"] = $this->listRights($user["data"]["aboutMe"]["dataSetMetadataList"]);
        }
        return $retArray;
    }

    private function listRights($list)
    {
        $retArray = array();

        if (count($list)) {
            foreach ($list as $element) {
                $retArray[$element["dataSetId"]] = $this->fillRightsList($element["userPermissions"]);
            }
        }
        return $retArray;
    }

    private function fillRightsList($rightsArray)
    {
        $retArray = array(
            "REMOVE_DATASET" => false,
            "CREATE" => false,
            "PUBLISH_DATASET" => false,
            "EDIT_COLLECTION_METADATA" => false,
            "EDIT_DATASET_METADATA" => false,
            "WRITE" => false,
            "DELETE" => false
        );
        foreach ($rightsArray as $right) {
            if (isset($retArray[$right])) {
                $retArray[$right] = true;
            }
        }
        return $retArray;
    }

    function getDataSetName($dataset)
    {
        $json = "{dataSets { " . $dataset . " {metadata {dataSetName}}}}";
        $result = $this->get_graphql_data($json);

        // if (!is_null($result["data"]["dataSets"][$dataset]["metadata"]["title"])) {
        //     return $result["data"]["dataSets"][$dataset]["metadata"]["title"]["value"];
        // } else {
        return $result["data"]["dataSets"][$dataset]["metadata"]["dataSetName"];
    }

    function createCollectionItem($dataSet, $collection, $uri, $field, $value, $type)
    {
        $query = 'mutation EditEntity($uri: String!, $entity: ' . "{$dataSet}_{$collection}" . 'CreateInput!) {dataSets {' . $dataSet . ' {' . $collection . ' {create(uri: $uri entity: $entity) { ' . $this->valOrList($field) . '}}}}}';

        if (substr($field, strlen($field) - 4) == "List") {
            $vars = "{\"uri\": \"$uri\", \"entity\": {\"creations\": {\"$field\": [{\"type\": \"xsd_string\", \"value\": \"$value\"}]}}}";
        } else {
            $vars = "{\"uri\": \"$uri\", \"entity\": {\"creations\": {\"$field\": {\"type\": \"xsd_string\", \"value\": \"$value\"}}}}";
        }
        $json = '{"query" : "' . $query . '" , "variables": ' . $vars . '}';
        $result = $this->set_graphql_data($json);
        return $result;
    }

    private function valOrList($field)
    {
        if (substr($field, strlen($field) - 4) == 'List') {
            return $field . '{ items {value}} ';
        } else {
            return $field . '{value} ';
        }
    }

    function getTotalFromList($set, $list)
    {
        $json = "query total { dataSets { $set { $list { total }}}}";
        $result = $this->get_graphql_data($json);
        return $result["data"]["dataSets"][$set][$list]["total"];
    }

    function get_graphql_data($json)
    {
        $options = array();
        $options[] = 'Accept: application/json';
        $options[] = 'Authorization: fake';

        $ch = curl_init(TIMBUCTOO_SERVER . urlencode($json));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $options);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
        curl_setopt($ch, CURLOPT_VERBOSE, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $response = curl_exec($ch);
        curl_close($ch);
        return $this->json2array($response);
    }

    function set_graphql_data($json_struc)
    {
        $options = array('Content-type: application/json', 'Content-Length: ' . strlen($json_struc));
        if (isset($_SESSION["hsid"])) {
            $options[] = 'Authorization: ' . $_SESSION["hsid"];
        }
        $ch = curl_init(TIMBUCTOO_SERVER);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $options);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json_struc);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json_struc);
        curl_setopt($ch, CURLOPT_VERBOSE, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $response = curl_exec($ch);
        curl_close($ch);
        $timArray = $this->json2array($response);
        return $timArray;
    }

    function getCollectionTitle($dataset, $collID)
    {
        $json = "query CollectionTitle { dataSets {" . $dataset . "{metadata {collection(collectionId: \"{$collID}\") {title{value}}}}}}";
        $result = $this->get_graphql_data($json);
        if (isset($result["data"]["dataSets"][$dataset]["metadata"]["collection"]["title"]["value"])) {
            return $result["data"]["dataSets"][$dataset]["metadata"]["collection"]["title"]["value"];
        } else {
            return $collID;
        }
    }

    function allCollectionProps($dataset)
    {
        $json = '{dataSets {' . $dataset . ' {metadata {collectionList {items {uri collectionId summaryProperties {title {path {step direction}}} properties {items {uri name}}}}}}}}';
        return $this->get_graphql_data($json);
    }

    function getCollectionUri($dataset, $collID)
    {
        $json = "query CollectionTitle { dataSets {" . $dataset . "{metadata {collection(collectionId: \"{$collID}\") {uri}}}}}";
        $result = $this->get_graphql_data($json);
        if (isset($result["data"]["dataSets"][$dataset]["metadata"]["collection"]["uri"])) {
            return $result["data"]["dataSets"][$dataset]["metadata"]["collection"]["uri"];
        } else {
            return $collID;
        }
    }

    function setCollectionTitle($datasetID, $collID, $newTitle)
    {
        $uri = $this->getCollectionUri($datasetID, $collID);
        $query = 'mutation SetCollectionMetadata($dataSetId:String! $collectionUri:String! $metadata:CollectionMetadataInput!) {setCollectionMetadata(dataSetId:$dataSetId collectionUri: $collectionUri metadata: $metadata) {uri title {value}}}';
        $vars = array("dataSetId" => $datasetID, "collectionUri" => $uri, "metadata" => array("title" => $newTitle));
        $json = array("query" => $query, "variables" => $vars);
        $json = json_encode($json);
        return $this->set_graphql_data($json);
    }

    function setCollectionItemTitle($dataset, $collection, $newTitle)
    {
        $query = 'mutation title($datasetid: String!, $collection: String!, $props: SummaryPropertiesInput!) {setSummaryProperties(dataSetId: $datasetid, collectionUri: $collection, summaryProperties: $props) {title {path {step direction}}}}';
        $str = '{ "datasetid": "%s", "collection": "%s", "props": {"title": {"path": [{"step": "%s", "direction":"OUT"}], "type": "DirectionalPath"}}}';
        $vars = sprintf($str, $dataset, $collection, $newTitle);
        $str = '{"query": "%s", "variables": %s}';
        $json = sprintf($str, $query, $vars);
        return $this->set_graphql_data($json);
    }

    function publish_dataset($id)
    {
        $query = 'mutation publish($dataSet: String!) { publish(dataSetId: $dataSet)  {dataSetId}}';
        $str = '{"dataSet": "%s"}';
        $vars = sprintf($str, $id);
        $str = '{"query": "%s", "variables": %s}';
        $json = sprintf($str, $query, $vars);
        $msg = $this->set_graphql_data($json);
        return $msg;
    }

    function getMetadataQueryVars($input, $id)
    {
        $retArray = array();
        $retArray["dataSet"] = $id;
        $retArray["metadata"] = $this->parseInput($input[0]["content"]);
        return $retArray;
    }

    /*function getCollectionInputFields($type)
    {
        return $this->get_graphql_data($this->json4objectReplacementInputFields($type));
    }*/

    function buildObjectDisplayQuery($dataSet, $object, $uri)
    {
        $type = "{$dataSet}_{$object}";
        $objectFields = $this->get_graphql_data($this->json4objectFields($type));
        $objectInputFields = $this->get_graphql_data($this->json4objectReplacementInputFields($type));
        $objectDisplayFields = $this->json4displayFields($objectFields, $objectInputFields);
        $json = $this->createObjectDisplayQuery($objectDisplayFields, $dataSet, $object, $uri);

        return $json;
    }

    function buildCreationFields($dataset, $object)
    {
        $type = "{$dataset}_{$object}";
        $objectFields = $this->get_graphql_data($this->json4objectFields($type));
        $objectInputFields = $this->get_graphql_data($this->json4objectCreationInputFields($type));
        $indexedObjectFields = $this->makeSchemaAdressable($objectFields);
        $indexedObjectInputFields = $this->makeSchemaAdressable($objectInputFields, "inputFields");
        return $this->filterObjectDisplayFields($indexedObjectFields, $indexedObjectInputFields);
    }

    function dropItem($dataset, $collection, $item)
    {
        $json = "mutation delete {dataSets { $dataset { $collection {delete(uri: \"$item\") {uri}}}}}";
        $uri = $this->get_graphql_data($json);
        return $uri;
    }

    public function updateCollectionItem($dataSet, $collection, $uri, $fields, $inputFieldTypes) {
        $fieldList = array();
        $valueList = array();

        foreach ($fields as $field) {
            if (substr($field["name"], -4) <> "List") {
                $fieldList[] = "{$field["name"]} {value}";
                $valueList[] = "\"{$field["name"]}\": {\"type\": \"{$inputFieldTypes[$field["name"]]}\", \"value\": \"{$field["content"][0]["value"]}\"}";
            } else {
                $fieldList[] = "{$field["name"]} {items {value}}";
                $valueList[] = "\"{$field["name"]}\": " . $this->getMultiValuedField($field, $inputFieldTypes);
            }
        }

        $query = "mutation EditEntity (\$uri: String! \$entity: {$dataSet}_{$collection}EditInput!) {dataSets { $dataSet { $collection {edit(uri: \$uri entity: \$entity) { " . implode(",", $fieldList) . "}}}}}";
        $vars = "{\"uri\": \"$uri\", \"entity\": {\"replacements\": {". implode(",", $valueList) ."}}}";
        $str = '{"query": "%s", "variables": %s}';
        $json = sprintf($str, $query, $vars);
        error_log($json);
        return $this->set_graphql_data($json);
    }

    private function getMultiValuedField($field, $types) {
        $items = array();
        $type = $types[$field["name"]];

        foreach ($field["content"] as $item) {
            $items[] = "{\"type\": \"$type\", \"value\": \"{$item["value"]}\"}";
        }
        return "[" . implode(",", $items) . "]";
    }

    public function  editQuery($collectionName, $dataSet, $field)
    {
        return "mutation EditEntity (\$uri: String! \$entity: {$dataSet}_{$collectionName}EditInput!) {dataSets { $dataSet { $collectionName {edit(uri: \$uri entity: \$entity) { $field {value}}}}}}";
    }

    public function editListQuery($collectionName, $dataSet, $field)
    {
        return "mutation EditEntity (\$uri: String! \$entity: {$dataSet}_{$collectionName}EditInput!) {dataSets { $dataSet { $collectionName {edit(uri: \$uri entity: \$entity) { $field {items {value}}}}}}}";
    }

    public function valueVariables($uri, $field, $value, $type)
    {
        return array("uri" => $uri, "entity" => array("replacements" => array($field => array("type" => $type, "value" => $value))));
    }

    public function multiValueVariables($uri, $field, $values)
    {
        $valsArr = array();
        foreach ($values as $value) {
            $valsArr[] = array("type" => "xsd_string", "value" => $value["value"]);
        }
        return array("uri" => $uri, "entity" => array("replacements" => array($field => $valsArr)));
    }

    private function createObjectDisplayQuery($objectDisplayFields, $dataSet, $object, $uri)
    {
        $fields = $this->createObjectDisplayQueryFields($objectDisplayFields, $dataSet);
        $query = '{dataSets {' . $dataSet . ' {' . $object . '(uri: "' . $uri . '") ' . $fields . '}}}';
        return $query;
    }

    private function createObjectDisplayQueryFields($objectDisplayFields, $type)
    {
        $fields = "";
        foreach ($objectDisplayFields as $field => $value) {
            if (strpos($value["name"], "Union_") !== 0) {
                switch ($value["name"]) {
                    case $type . "_value_schema_latitude":
                    case $type . "_value_schema_longitude":
                    case $type . "_value_custom_datable":
                    case $type . "_value_xsd_string":
                        $fields .= $field . '{value, type} ';
                        break;
                    case $type . "_value_xsd_string_List":
                        $fields .= $field . '{items{value, type}} ';
                        break;
                    default:
                        if (strpos($field, 'List')) {
                            if (strpos($value["name"], '_value_')) {
                                $fields .= $field . '{items{value, type}} ';
                            } else {
                                $fields .= $field . '{items{uri title{value, type}}} ';
                            }
                        }
//                        else {
//                            $fields .= $field . '{uri title{value}} ';
//                        }
                        break;
                }
            }
        }
        return '{' . $fields . '}';
    }

    private function json4displayFields($objectFields, $objectInputFields)
    {
        //$retArray = array();
        $indexedObjectFields = $this->makeSchemaAdressable($objectFields);
        $indexedObjectInputFields = $this->makeSchemaAdressable($objectInputFields, "inputFields");
        return $this->filterObjectDisplayFields($indexedObjectFields, $indexedObjectInputFields);
    }

    private function filterObjectDisplayFields($indexedObjectFields, $indexedObjectInputFields)
    {
        $retArray = array();
        foreach ($indexedObjectInputFields as $fieldName => $field) {
            $retArray[$fieldName] = $indexedObjectFields[$fieldName];
        }
        return $retArray;
    }

    private function json4objectFields($object)
    {
        return "query fields{__type(name: \"$object\") {name fields {name type { name description ofType {name description}}}}}";
    }

    private function json4objectReplacementInputFields($object)
    {
        return "query inputfields{__type(name: \"{$object}ReplacementsInput\") {name inputFields {name type {name description}}}}";
    }

    private function json4objectCreationInputFields($object)
    {
        return "query inputfields{__type(name: \"{$object}CreationsInput\") {name inputFields {name type {name description}}}}";
    }

    private function parseInput($input)
    {
        $retArray = array();
        foreach ($input as $element) {
            if ($element["type"] == 'element') {
                $retArray[$element["name"]] = $element["content"][0]["value"];
            } else {
                $retArray[$element["name"]] = $this->parseInput($element["content"]);
            }
        }
        return $retArray;
    }

}
