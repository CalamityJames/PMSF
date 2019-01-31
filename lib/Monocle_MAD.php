<?php

namespace Scanner;

// Extends Alternate as that's what it's based on

class Monocle_MAD extends Monocle_Alternate
{

    public function get_stops($swLat, $swLng, $neLat, $neLng, $tstamp = 0, $oSwLat = 0, $oSwLng = 0, $oNeLat = 0, $oNeLng = 0, $lured = false)
    {
        $conds = array();
        $params = array();

        $conds[] = "lat > :swLat AND lon > :swLng AND lat < :neLat AND lon < :neLng";
        $params[':swLat'] = $swLat;
        $params[':swLng'] = $swLng;
        $params[':neLat'] = $neLat;
        $params[':neLng'] = $neLng;

        if ($oSwLat != 0) {
            $conds[] = "NOT (lat > :oswLat AND lon > :oswLng AND lat < :oneLat AND lon < :oneLng)";
            $params[':oswLat'] = $oSwLat;
            $params[':oswLng'] = $oSwLng;
            $params[':oneLat'] = $oNeLat;
            $params[':oneLng'] = $oNeLng;
        }
        if ($tstamp > 0) {
            $conds[] = "updated > :lastUpdated";
            $params[':lastUpdated'] = $tstamp;
        }
        return $this->query_stops($conds, $params);
    }

    public function query_stops($conds, $params)
    {
        global $db;

        $query = "(SELECT DISTINCT
        pokestops.external_id AS pokestop_id,
        pokestops.lat AS latitude,
        pokestops.lon AS longitude,
        trs_quest.quest_type,
        trs_quest.quest_stardust,
        trs_quest.quest_pokemon_id,
        trs_quest.quest_reward_type,
        trs_quest.quest_item_id,
        trs_quest.quest_item_amount,
        pokestops.name AS pokestop_name,
        pokestops.url,
        trs_quest.quest_target,
        REPLACE(trs_quest.quest_condition, '\'', '\"') as quest_condition,
        trs_quest.quest_timestamp 
        FROM pokestops INNER JOIN trs_quest ON
                    (pokestops.external_id COLLATE utf8mb4_general_ci) = trs_quest.GUID WHERE
                    DATE(FROM_UNIXTIME(trs_quest.quest_timestamp,'%Y-%m-%d')) = CURDATE()
            AND :conditions)
            UNION
            (SELECT DISTINCT external_id AS pokestop_id,
        lat AS latitude,
        lon AS longitude,
        NULL AS quest_type,
        NULL AS quest_stardust,
        NULL AS quest_pokemon_id,
        NULL AS quest_reward_type,
        NULL AS quest_item_id,
        NULL AS quest_item_amount,
        pokestops.name AS pokestop_name,
        pokestops.url,
        NULL AS quest_target,
        NULL AS quest_condition,
        NULL AS quest_timestamp 
        FROM pokestops
        WHERE :conditions)";

        $query = str_replace(":conditions", join(" AND ", $conds), $query);
        $pokestops = $db->query($query, $params)->fetchAll(\PDO::FETCH_ASSOC);

        $data = array();
        $i = 0;

        foreach ($pokestops as $pokestop) {
            $pokestop["latitude"] = floatval($pokestop["latitude"]);
            $pokestop["longitude"] = floatval($pokestop["longitude"]);

            if ($pokestop["quest_condition"]) {
                $questStr = strtolower($pokestop["quest_condition"]);
                //print $questStr;die();
                $quest_condition = json_decode($questStr);
                $quest = $quest_condition[0];

                if (is_a($quest, 'stdClass')) {
                    switch($quest->type) {
                        case 1:
                            // type one is catch specific pokemon types
                            $questData = $quest->with_pokemon_type->pokemon_type;
                            break;
                        case 2:
                            // type 2 is catch specific pokemon
                            $questData = $quest->with_pokemon_category->pokemon_ids;
                            break;
                        case 7:
                            // type 7 is win a certain raid battle level
                            $questData = $quest->with_raid_level->raid_level;
                            break;
                        case 8:
                            // type 8 is make a certain type of throw
                            $questData = $quest->with_throw_type->throw_type;
                            // throwtypes:
                            // 10: nice
                            // 11: great
                            // 12: excellent(?)
                            break;
                        case 11:
                            // type 11 is use something catching a pokemon
                            // or evolve a pokemon, if not set
                            $questData = $quest->with_item->item;
                            if (!$questData) {
                                // it's an evolve task
                            }
                            break;
                        case 14:
                            // type 14 is make a certain number of throws in a row
                            $questData = $quest->with_throw_type->throw_type;
                            // throwtypes:
                            // 10: nice
                            // 11: great
                            // 12: excellent(?)
                            break;
                        default:
                            // everything else has no other specifics
                            break;
                    }
                    $pokestop["quest_data_type"] = $quest->type;
                    $pokestop["quest_data"] = $questData;
                }
            }
            $data[] = $pokestop;

            unset($pokestops[$i]);
            $i++;
        }
        return $data;
    }

    public function get_spawnpoints($swLat, $swLng, $neLat, $neLng, $tstamp = 0, $oSwLat = 0, $oSwLng = 0, $oNeLat = 0, $oNeLng = 0)
    {
        $conds = array();
        $params = array();

        $conds[] = "latitude > :swLat AND longitude > :swLng AND latitude < :neLat AND longitude < :neLng";
        $params[':swLat'] = $swLat;
        $params[':swLng'] = $swLng;
        $params[':neLat'] = $neLat;
        $params[':neLng'] = $neLng;

        if ($oSwLat != 0) {
            $conds[] = "NOT (latitude > :oswLat AND longitude > :oswLng AND latitude < :oneLat AND longitude < :oneLng)";
            $params[':oswLat'] = $oSwLat;
            $params[':oswLng'] = $oSwLng;
            $params[':oneLat'] = $oNeLat;
            $params[':oneLng'] = $oNeLng;
        }
        if ($tstamp > 0) {
            $conds[] = "last_non_scanned > :lastUpdated";
            $params[':lastUpdated'] = date("Y-m-d H:i:s", $tstamp);
        }

        return $this->query_spawnpoints($conds, $params);
    }

    private function query_spawnpoints($conds, $params)
    {
        global $db;
        $query = "SELECT latitude, 
        longitude, 
        spawnpoint AS spawnpoint_id,
        calc_endminsec,
        NULL AS duration
        FROM   trs_spawn 
        WHERE :conditions";

        $query = str_replace(":conditions", join(" AND ", $conds), $query);
        $spawnpoints = $db->query($query, $params)->fetchAll(\PDO::FETCH_ASSOC);

        $data = array();
        $i = 0;

        foreach ($spawnpoints as $spawnpoint) {
            $spawnpoint["latitude"] = floatval($spawnpoint["latitude"]);
            $spawnpoint["longitude"] = floatval($spawnpoint["longitude"]);
            // convert the calculated spawn times back to seconds. fun!
            $retime = explode(":", $spawnpoint['calc_endminsec']);
            $spawnpoint["time"] = intval(($retime[0] * 60) + $retime[1]);
            unset($retime, $spawnpoint["calc_endminsec"]);
            $spawnpoint["duration"] = intval($spawnpoint["duration"]);
            $data[] = $spawnpoint;

            unset($spawnpoints[$i]);
            $i++;
        }
        return $data;
    }


}
