<?php

class Timwrite {

    function makeOutputArray($queryOutput, $schema, $dataset, $object, $tweakIndex = array()) {
        $retArray = array();
        
        foreach ($queryOutput["data"]["dataSets"][$dataset][$object] as $key => $value) {
            $newKey = $this->getLabel($key, $tweakIndex);
            if (isset($value["value"])) {
                $retArray[$newKey] = array("key" => $newKey, "value" => $value["value"]);
            } else {
                if (isset($value["items"])) {
                    $linkObject = $this->_stripObject($schema[$key]["name"], $dataset);
                    $retArray[$newKey] = array("key" => $newKey, "value" => $this->enumList($value["items"], $dataset, $linkObject));
                } else {
                    if (isset($value["title"])) {
                        if (isset($value["uri"]) && isset($schema[$key]["name"])) {
                            $linkObject = $this->_stripObject($schema[$key]["name"], $dataset);
                            $retArray[$newKey] = array("key" => $newKey, "value" => "<a href=\"" . BASE_URL . "show/$dataset/$linkObject/" . base64_encode($value["uri"]) . "\">" . $value["title"]["value"] . "</a>");
                        } else {
                            $retArray[$newKey] = array("key" => $newKey, "value" => $value["title"]["value"]);
                        }
                    }
                }
            }
            if (isset($tweakIndex["fields"][$key]["attributes"]["displayOrder"])) {
                $retArray[$newKey]["displayOrder"] = $tweakIndex["fields"][$key]["attributes"]["displayOrder"];
            }
            if (!isset($retArray[$newKey]["value"])) {
                $retArray[$newKey]["value"] = "";
                $retArray[$newKey]["key"] = $newKey;
            }
        }
        return $this->_displaySort($retArray);
    }
    
    public function makeEditArray($queryOutput, $schema, $dataset, $object, $tweakIndex = array()) {
        $retArray = array();
       
        foreach ($queryOutput["data"]["dataSets"][$dataset][$object] as $key => $value) {
            $newKey = $this->getLabel($key, $tweakIndex);
            if (isset($value["value"])) {
                $retArray[$key] = array("name" => $key, "key" => $newKey, "value" => $value["value"], "type" => $value["type"]);
            } else {
                if (isset($value["items"])) {
                    $retArray[$key] = array("name" => $key, "key" => $newKey, "items" => $value["items"]);
                } else {
                    if (isset($value["uri"])) {
                        $retArray[$key] = array("name" => $key, "key" => $newKey, "uri" => $value["uri"]);
                    }
                }
            }
            if (isset($tweakIndex["fields"][$key]["attributes"]["displayOrder"])) {
                $retArray[$key]["displayOrder"] = $tweakIndex["fields"][$key]["attributes"]["displayOrder"];
            }
            if (!isset($retArray[$key]["value"]) && !isset($retArray[$key]["items"])) {
                $retArray[$key]["name"] = $key;
                $retArray[$key]["value"] = "";
                $retArray[$key]["key"] = $newKey;
            }
        }
        return $this->_displaySort($retArray); 
    }
    
    public function makeCreateArray($fields, $tweakIndex) {
        $retArray = array();
        foreach ($fields as $key => $value) {
            $newKey = $this->getLabel($key, $tweakIndex);
            if (substr($value["name"], strlen($value["name"])-5) == "_List") {
                 $retArray[$key] = array("name" => $key, "key" => $newKey, "items" => array());
            } else {
                $retArray[$key] = array("name" => $key, "key" => $newKey, "value" => "", "type" => "");
            }
            if (isset($tweakIndex["fields"][$key]["attributes"]["displayOrder"])) {
                $retArray[$key]["displayOrder"] = $tweakIndex["fields"][$key]["attributes"]["displayOrder"];
            }
            if (!isset($retArray[$key]["value"]) && !isset($retArray[$key]["items"])) {
                $retArray[$key]["name"] = $key;
                $retArray[$key]["value"] = "";
                $retArray[$key]["key"] = $newKey;
            }
        }
        return $this->_displaySort($retArray); 
    }
    
    

    /*
     * Private functions
     */
    
     private function _stripObject($obj, $set){
         $retString = str_replace("{$set}_", "", $obj);
         $retString = str_replace("_List", "", $retString);
         return $retString;
     }
     
     private function _displaySort($arr) {
        $retArr = array();
        $tempArr = array();
        foreach ($arr as $key => $element) {
                if (isset($element["displayOrder"])) {
                    $retArr[$key] = $element;
                } else {
                    $tempArr[$key] = $element;
                }
            
        }
        uasort($retArr, array(__CLASS__, 'cmp'));
        foreach ($tempArr as $key => $element) {
            $retArr[$key] = $element;
        }
        return $retArr;
    }

    static function cmp($a, $b) {
        if ($a["displayOrder"] == $b["displayOrder"]) {
            return 0;
        } else {
            return ($a["displayOrder"] < $b["displayOrder"]) ? -1 : 1;
        }
    }

    private function enumList($listArray, $dataset, $linkObject) {
        $retArray = array();

        foreach ($listArray as $element) {
            if (isset($element["value"])) {
                $retArray[] = $element["value"];
            }else{
                if (isset($element["uri"]) && isset($element["title"])) {
                   $retArray[] = "<a href=\"" . BASE_URL . "show/$dataset/$linkObject/" . base64_encode($element["uri"]) . "\">" . $element["title"]["value"] . "</a>";
                }
            }
        }
        return implode("<br>", $retArray);
    }
    
    private function getLabel($key, $tweak){
        if (isset($tweak["fields"][$key]["attributes"]["label"])) {
            return $tweak["fields"][$key]["attributes"]["label"];
        }else{
            return $key;
        }
    }

}
