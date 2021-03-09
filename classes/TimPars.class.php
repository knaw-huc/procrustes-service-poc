<?php

if (!defined('BASE_URL'))
    exit('No direct script access allowed');

class Timpars {

    function json2array($json) {
        return json_decode($json, 'JSON_OBJECT_AS_ARRAY');
    }

    function parse($obj, $single, $tq = null) {

        if ($single) {
            $retObj = array("id" => "dummy", "content" => array(), "record" => array(array(), array(), array(), array()));
            $retObj["content"] = $this->_parse_single($obj);
            $tempArr = $this->_parse_single_rec($obj);
            $retObj["record"][2] = $tempArr[0];
        } else {
            $retObj = array("id" => "dummy", "content" => array());
            $retObj["content"] = $this->_parse_object($obj, $tq, true);
        }
        return $retObj;
    }

    function getValueFromStruc($struc) {
        $arr = $this->json2array($struc);
        return $this->_get_value_from_struc($arr);
    }

    /*
     * Temporary methods for demo purpose
     */

    function parsePlace($obj, $uri) {
        $retObj = array("id" => $uri, "content" => array(), "record" => array(array(), array(), array(), array()));
        $retObj["content"] = $this->_parseDemoPlace($obj["data"]["dataSets"]["u33707283d426f900d4d33707283d426f900d4d0d__hpp6demo"]["clusius_Places"]);
        $retObj["record"][2] = $this->_setPlaceValues($obj["data"]["dataSets"]["u33707283d426f900d4d33707283d426f900d4d0d__hpp6demo"]["clusius_Places"]);
        return $retObj;
    }

    private function _parseDemoPlace($obj) {
        $retArray = array();
        $tl = new Timlabel();
        $nodeArray = array(
            "type" => "Component",
            "level" => 1,
            "ID" => uniqid(),
            "attributes" => array(
                "name" => "clusius_Places",
                "label" => $tl->getLabel("clusius_Places"),
                "CardinalityMin" => '1',
                "CardinalityMax" => '1',
                "initialOrder" => "0"),
            "content" => $this->_parseDemoArray($obj, $tl)
        );
        $retArray[] = $nodeArray;
        return $retArray;
    }
    
    private function _setPlaceValues($obj) {
        $retArray = array(
            "name" => 'clusius_Places',
            "type" => "component"
        );
        $nodeArray = array();
        foreach ($obj as $key => $value) {
            if (is_array($value) && key_exists("value", $value)) {
                $nodeArray[] = array(
                    "name" => $key,
                    "type" => 'element',
                    "value" => $value["value"]
                );
            }
        }
        if (count($obj["skos_altLabelList"]["items"])) {
            $nodeArray[] = array(
                "name" => "skos_altLabelList",
                "type" => "component",
                "value" => $this->_getPlaceListItems($obj["skos_altLabelList"]["items"])
            );
        }
        $retArray["value"] = $nodeArray;
        return $retArray;
    }

    private function _getPlaceListItems($obj) {
        $retArray = array();
        foreach ($obj as $item) {
            $retArray[] = array(
                    "name" => "value",
                    "type" => 'element',
                    "value" => $item["value"]
                );
        }
        return $retArray;
    }
    
    private function _parseDemoArray($obj, $tl) {
        $retArray = array();
        foreach ($obj as $key => $value) {
            $retArray[] = $this->_buildField($key, $value, $tl);
        }
        return $retArray;
    }

    private function _buildField($key, $el, $tl) {
        $retArray = array(
            "type" => "Element",
            "level" => 2,
            "ID" => uniqid(),
            "attributes" => array(
                "name" => "$key",
                "label" => $tl->getLabel($key),
                "CardinalityMin" => '1',
                "CardinalityMax" => '1',
                "initialOrder" => "0")
        );
        switch ($key) {
            case 'tim_name':
                $retArray["attributes"]["width"] = 60;
                $retArray["attributes"]["ValueScheme"] = 'string';
                break;
            case 'tim_country':
                $retArray["attributes"]["width"] = 60;
                $retArray["attributes"]["ValueScheme"] = 'string';
                $retArray["attributes"]["CardinalityMin"] = 0;
                break;
            case 'tim_longitude':
            case 'tim_latitude':
                $retArray["attributes"]["width"] = 20;
                $retArray["attributes"]["CardinalityMin"] = 0;
                $retArray["attributes"]["ValueScheme"] = 'int';
                break;
            case 'tim_remarks':
                $retArray["attributes"]["width"] = 60;
                $retArray["attributes"]["height"] = 8;
                $retArray["attributes"]["ValueScheme"] = 'string';
                $retArray["attributes"]["inputField"] = 'multiple';
                $retArray["attributes"]["CardinalityMin"] = 0;
                break;
            case 'skos_altLabelList':
                $retArray["type"] = 'Component';
                $retArray["attributes"]["CardinalityMin"] = 0;
                $retArray["content"] = array(
                    "type" => "Element",
                    "level" => 3,
                    "ID" => uniqid(),
                    "attributes" => array(
                        "name" => "value",
                        "label" => "Name",
                        "CardinalityMin" => '0',
                        "CardinalityMax" => '1',
                        "ValueScheme" => 'string',
                        "duplicate" => "yes",
                        "initialOrder" => "0"
                        )
                );
                break;
        }
        return $retArray;
    }

    /*
     * Private functions
     */

    private function _parse_object($obj, $tq, $mandatory, $level = 0) {
        $retArray = array();
        $obj = $obj["data"]["__type"];
        $level++;
        $tl = new Timlabel();
        $nodeArray = array(
            "type" => "Component",
            "level" => $level,
            "ID" => uniqid(),
            "attributes" => array(
                "name" => $obj["name"],
                "label" => $tl->getLabel($obj["name"]),
                "CardinalityMin" => '0',
                "CardinalityMax" => '1'),
            "content" => $this->_parse_schema($obj["fields"], $tq, $tl)
        );
        if ($mandatory) {
            $nodeArray["attributes"]["CardinalityMin"] = 1;
        }
        return $nodeArray;
    }

    private function _parse_schema($obj, $tq, $tl, $level = 0) {
        $level++;
        $retArray = array();
        foreach ($obj as $value) {
            switch ($value["type"]["kind"]) {
                case "NON_NULL":
                    switch ($value["type"]["ofType"]["kind"]) {
                        case "SCALAR":
                            $retArray[] = $this->_set_none_null_element($value, $level, $tl);
                            break;
                        case "OBJECT":
                            $newObj = $tq->get_graphql_data($tq->getSchema($value["type"]["ofType"]["name"]));
                            $retArray[] = $this->_parse_object($newObj, $tq, true, $level);
                            break;
                    }
                    break;
                case "OBJECT":
                    $newObj = $tq->get_graphql_data($tq->getSchema($value["type"]["name"]));
                    $retArray[] = $this->_parse_object($newObj, $tq, false, $level);
                    break;
//                case "INTERFACE":
//                    $retArray = $this->_set_interface($value, $level, $tl);
//;                    break;
            }
        }
        return $retArray;
    }

    private function _set_none_null_element($obj, $level, $tl) {
        $retArray = array(
            "type" => "Element",
            "level" => $level,
            "ID" => uniqid(),
            "attributes" => array(
                "name" => $obj["name"],
                "label" => $tl->getLabel($obj["name"]),
                "CardinalityMin" => '1',
                "CardinalityMax" => '1')
        );
        if ($obj["type"]["ofType"]["name"] == "Boolean") {
            $retArray["attributes"]["ValueScheme"] = "boolean";
        } else {
            $retArray["attributes"]["ValueScheme"] = "string";
        }
        return $retArray;
    }

    private function _set_interface($obj, $level, $tl) {
        $retArray = array(
            "type" => "Element",
            "level" => $level,
            "ID" => uniqid(),
            "attributes" => array(
                "name" => $obj["name"],
                "label" => $tl->getLabel($obj["name"]),
                "CardinalityMin" => '1',
                "CardinalityMax" => '1',
                "ValueScheme" => "string"
            )
        );
        return $retArray;
    }

    private function _parse_single($obj, $level = 0) {
        $level++;
        $retArray = array();
        foreach ($obj as $key => $value) {
            if (is_array($value)) {
                $nodeArray = array(
                    "type" => "Component",
                    "level" => $level,
                    "ID" => uniqid(),
                    "attributes" => array(
                        "name" => $key,
                        "label" => '',
                        "CardinalityMin" => '1',
                        "CardinalityMax" => '1'),
                    "content" => $this->_parse_single($value, $level)
                );
            } else {
                $nodeArray = array(
                    "type" => "Element",
                    "level" => $level,
                    "ID" => uniqid(),
                    "attributes" => array(
                        "name" => $key,
                        "label" => '',
                        "CardinalityMin" => '1',
                        "CardinalityMax" => '1',
                        "ValueScheme" => 'string',
                        "inputField" => 'single')
                );
            }
            $retArray[] = $nodeArray;
        }
        return $retArray;
    }

    private function _devalue($key) {
        if ($key == 'value') {
            return "nameval";
        } else {
            return $key;
        }
    }

    private function _parse_single_rec($obj) {
        $retArray = array();
        foreach ($obj as $key => $value) {
            if (is_array($value)) {
                $nodeArray = array(
                    "name" => $key,
                    "type" => "component",
                    "value" => $this->_parse_single_rec($value)
                );
            } else {
                $nodeArray = array(
                    "name" => $key,
                    "type" => "element",
                    "value" => $value
                );
            }
            $retArray[] = $nodeArray;
        }
        return $retArray;
    }

    private function _get_value_from_struc($arr) {
        foreach ($arr as $key => $value) {
            if (is_array($value)) {
                return $this->_get_value_from_struc($value);
            } else {
                if ($key == 'value') {
                    return $arr[$key];
                }
            }
        }
    }

}
