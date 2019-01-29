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
        trs_quest.quest_condition,
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
            $data[] = $pokestop;

            unset($pokestops[$i]);
            $i++;
        }
        return $data;
    }

    // todo: Full MAD spawnpoint support

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
        REPLACE(calc_endminsec, ':', '') AS despawn_time,
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
            $spawnpoint["time"] = intval($spawnpoint["despawn_time"]);
            $spawnpoint["duration"] = intval($spawnpoint["duration"]);
            $data[] = $spawnpoint;

            unset($spawnpoints[$i]);
            $i++;
        }
        return $data;
    }


}
