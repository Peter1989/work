<?php
//国内版5.2.8发现首页动态通知常量
define('MAX_AVATAR_NUM', 10);
define('MAX_DISTANCE', 500);
define('HOT_BACK_HOURS', 8);

explore_ttinform_main();

function explore_ttinform_main(){
    $me = intval($_REQUEST['me']);
    $uid = intval($_REQUEST['uid']);
    $offset = 0;
    $size = MAX_AVATAR_NUM;
    $inform_type = 0;
    $users_info = array();
    if($me != $uid){
        return array(API_CODE_FORBIDDEN, array(
                    'code' => API_CODE_FORBIDDEN,
                    'message' => "",
                    ));
    }

    //用户关注的动态有更新
    $users_info = get_users_of_follows_tt($uid, $offset, $size);
    if($users_info){
        $inform_type = 1;
        http_response_message(API_CODE_OK, array(
                    'code'      => API_CODE_OK,
                    'message'   => '',
                    'data'      => array('inform' => $inform_type, 'inform_users' => $users_info),
                    ));
    }
    //用户广场的动态有更新
    $tt_hits = get_tts_of_square($uid, $offset, $size);

    $users = array();
    foreach($tt_hits as $k => $v){
        $users[] = $v['_source']['uid'];
    }
    $users = array_unique($users);
    $users_info = format_users_by_uid($users);

    if($users_info){
        $inform_type = 2;
        http_response_message(API_CODE_OK, array(
                    'code'      => API_CODE_OK,
                    'message'   => '',
                    'data'      => array('inform' => $inform_type, 'inform_users' => $users_info),
                    ));
    }

    //用户关注和广场的动态都没有更新
    http_response_message(API_CODE_OK, array(
                'code'      => API_CODE_OK,
                'message'   => '',
                'data'      => array(),
                ));

}

function get_users_of_follows_tt($uid, $offset, $size){

    $join_sql = " AND ( feed_status=201 OR feed_status=200 ) ";
    //从上次用户浏览关注动态为起始时间，看关注动态有没有更新
    $redis = redis_get_instance("USERS", 0);
    $set_last = $redis->EXISTS("u:$uid:follows_tt_last_time");
    if(!empty($set_last)){
        $last_time = $redis->GET("u:$uid:follows_tt_last_time");
        $join_sql .=" AND ( feed_timestamp > $last_time ) ";
    }
    $data = users_get_followed($uid, 400);

    if($data){
        $follows = implode(",", $data);
    }else{
        $follows = '';
    }

    $list = array();
    if($follows){
        $conn = mysqli_get_slave();
        if ($conn === false) {
            LOG_WF("mysql", __FILE__.":".__LINE__, mysqli_connect_error());
            http_response_message(API_CODE_INTERNAL_SERVER_ERROR, array(
                        'code' => API_CODE_INTERNAL_SERVER_ERROR,
                        'message' => "Connect Failed, Error : " . mysqli_connect_error(),
                        ));
        }

        mysqli_query($conn, "SET NAMES 'UTF8MB4'");

        if (@mysqli_select_db($conn, BackendConf::$MYSQL['DATABASE']) === false) {
            LOG_WF("mysql", __FILE__.":".__LINE__, mysqli_error($conn));
            http_response_message(API_CODE_INTERNAL_SERVER_ERROR, array(
                        'code' => API_CODE_INTERNAL_SERVER_ERROR,
                        'message' => "Select DB Failed, Error : " . mysqli_error($conn),
                        ));
        }

        $sql = "SELECT feed_uid
            FROM feed
            WHERE (feed_uid IN ($follows)
                    $join_sql)
            ORDER BY feed_timestamp DESC LIMIT $size";
        $query = @mysqli_query($conn, $sql);

        if ($query === false) {
            LOG_WF("mysql", __FILE__.":".__LINE__, mysqli_error($conn), $sql);
            http_response_message(API_CODE_INTERNAL_SERVER_ERROR,
                    array(
                        "code" => API_CODE_INTERNAL_SERVER_ERROR,
                        "message" => "Query Failed, Error : " . mysqli_error($conn),
                        )
                    );
        }

        $users = array();
        while($v = @mysqli_fetch_assoc($query)){
            $users[] = $v['feed_uid'];
        }

        $ret = format_users_by_uid($users);
        return $ret;
    }
}

function get_tts_of_square($uid, $offset, $size){
    $users_info = array();
    $distance = MAX_DISTANCE;
    $back_hours = HOT_BACK_HOURS;
    $longitude = isset($_REQUEST['lot']) ? $_REQUEST['lot'] : 0;
    $latitude = isset($_REQUEST['lat']) ? $_REQUEST['lat'] : 0;

    if(!$longitude || !$latitude){
        $self_info  = users_get($uid);
        $latitude = $self_info['latitude'];
        $longitude = $self_info['longitude'];
    }
    $latitude = floatval($latitude);
    $longitude = floatval($longitude);
    $location = "$latitude,$longitude";

    if($uid % 2){
        $hits = get_squarett_time_sorted($uid, $location, $distance, $offset, $size, $back_hours);
    }else{
        $hits = get_squarett_hot_sorted($uid, $location, $distance, $offset, $size);
    }

    return $hits;
}

function get_squarett_hot_sorted($uid, $location, $distance, $offset, $size){
    $should[] = array(
            'term' => array(
                'status' => 200,
                ),
            );

    $should[] = array(
            'term' => array(
                'status' => 201,
                ),
            );

    $must[] = array(
            'geo_distance_range' => array(
                'geo' => '0.1m',
                'lte' => "{$distance}km",
                'location' => $location,
                ),
            );

    $must_not[] = array(
            'term' => array(
                'uid' => $uid,
                ),
            );

    $q = array(
            'query' => array(
                'filtered' => array(
                    'filter' => array(
                        'bool' => array(
                            'must' => $must,
                            'must_not' => $must_not,
                            'should' => $should,
                            ),
                        ),
                    ),
                ),
            'sort' => array(
                'ranking' => 'desc',
                '_geo_distance' => array(
                    'location' => $location,
                    'order' => 'asc',
                    'unit' => 'km',
                    'distance_type' => 'sloppy_arc',
                    ),
                ),
            );

    $result = _tt_search_by_es($q, $offset, $size);
    $hits = $result['hits']['hits'];
    return $hits;
}

function get_squarett_time_sorted($uid, $location, $distance, $offset, $size, $back_hours){
    $should[] = array(
            'term' => array(
                'status' => 200,
                ),
            );

    $should[] = array(
            'term' => array(
                'status' => 201,
                )
            );

    $must[] = array(
            'geo_distance_range' => array(
                'gte' => '0.1m',
                'lte' => "{$distance}km",
                'location' => $location,
                ),
            );

    $intval = 3600*$back_hours;
    $_8hago = time() - $intval;
    $must[] = array(
            'range' => array(
                'tttime' => array(
                    'gte' => $_8hago,
                    ),
                ),
            );

    $must_not[] = array(
            'term' => array(
                'uid' => $uid,
                ),
            );

    $q = array(
            'query' => array(
                'filtered' => array(
                    'filter' => array(
                        'bool' => array(
                            'must' => $must,
                            'must_not' => $must_not,
                            'should' => $should,
                            ),
                        ),
                    ),
                ),
            'sort' => array(
                'tttime' => 'desc',
                '_geo_distance' => array(
                    'location' => $location,
                    'order' => 'asc',
                    'unit' => 'km',
                    'distance_type' => 'sloppy_arc',
                    ),
                ),
            );

    $result = _tt_search_by_es($q, $offset, $size);
    $hits = $result['hits']['hits'];
    return $hits;
}
