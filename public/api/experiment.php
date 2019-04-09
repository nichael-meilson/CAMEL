<?php
class Experiment extends API {
    public function get($id, $params) {
        try {
            $where = array();
            $tokens = array();
            $sql = "SELECT e.`id` AS `experiment_id`, e.`name`, "
                 ."f.`id` AS `field_id`, f.`title` AS `field_title`, f.`weight`, "
                 ."CONCAT_WS('-',ef.`value_INT`,ef.`value_VARCHAR`,ef.`value_DOUBLE`,ef.`value_BOOL`,ef.`value_TEXT`) AS `value` "
                 ."FROM `experiments` e "
                 ."JOIN `experiments_fields` ef ON e.`id` = ef.`experiment_id` "
                 ."JOIN `fields` f ON ef.`field_id` = f.`id` ";
            
            $order = " ORDER BY e.`id`, f.`weight`";

            //fetch all fields/values for the experiments
            if (empty($id)) {
                $sql.= $order;
                $qry = $this->db->prepare($sql);
                $qry->setFetchMode(PDO::FETCH_ASSOC);
                $qry->execute(array());
                $res = $qry->fetchAll();                
            } else {
                $where[]= " experiment_id = :ID";
                $tokens[':ID'] = $id;

                $sql.=" WHERE ".implode(" AND ", $where);
                $qry = $this->db->prepare($sql);
                $qry->setFetchMode(PDO::FETCH_ASSOC);
                $qry->execute($tokens);
                $res = $qry->fetchAll();
            }

            //gather fields/values per experiment in an assoc array
            $summary = array();
            foreach ($res as $entry){
                $experiment_id = $entry['experiment_id'];
                if (!isset($summary[$experiment_id])){
                    $summary[$experiment_id] = array();
                    $summary[$experiment_id]['name'] = $entry['name'];
                    $summary[$experiment_id]['fields'] = array();
                    //$summary[$experiment_id]['groups'] = array();
                }

                $field_id = $entry['field_id'];
                $field_value = $entry['value'];
                if (!isset($summary[$experiment_id]['fields'][$field_id])){
                    $summary[$experiment_id]['fields'][$field_id] = array();
                }
                $summary[$experiment_id]['fields'][$field_id][] = $field_value;
            }

            //generate a list from gathered results and add the references to each entry
            $result = array();
            foreach ($summary as $exp_id => $exp){
                $sql = "SELECT r.`id`, r.`authors`, r.`title`, r.`journal`, r.`year`, r.`pages`, r.`pubmed_id`, r.`url` "
                     ."FROM `references` r "
                     ."JOIN `experiments_references` er ON r.`id` = er.`reference_id` "
                     ."WHERE er.`experiment_id` = :ID";
                
                $qry = $this->db->prepare($sql);
                $qry->setFetchMode(PDO::FETCH_ASSOC);
                $qry->execute(array(":ID" => $exp_id));
                $res = $qry->fetchAll();
                                
                $exp['id'] = $exp_id;
                $exp['references'] = $res;
                $result[] = $exp;
            }
            if (!empty($id)){
                $result = $result[0];
            }
            echo json_encode($result, JSON_NUMERIC_CHECK);
        } catch (PDOException $e) {
            echo "Error: ". $e->getMessage();
        }
    }
}
