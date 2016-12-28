<?php

require_once('groups_recommend.php');

//推荐群组主函数
function recommend_main(){
    $ALLOW_ACTIONS = array('language');
    $filter = isset($_REQUEST['filter']) ? trim($_REQUEST['filter']) : '';
    $valid = in_array($filter, $ALLOW_ACTIONS);
    if($valid === false){
        http_response_message(API_CODE_FORBIDDEN, array(
                    "code"  => API_CODE_FORBIDDEN,
                    "message"   => "",
                    )
                );
    }

    $page = isset($_REQUEST['page']) ? intval($_REQUEST['page']) : 1;
    $offset = isset($_REQUEST['offset']) ? intval($_REQUEST['offset']) : 0;
    $size = isset($_REQUEST['size']) ? intval($_REQUEST['size']) : 15;
    $origin = isset($_REQUEST['origin']) ? intval($_REQUEST['origin']) : 0;

    list($list, $offset_next, $hasmore, $origin) = recommend_by_language(false, $page, $offset, $size, $origin);    

    http_response_message(API_CODE_OK,
            array(
                "code"  => API_CODE_OK,
                "message" => "",
                "data"  => array('groups_recommend' => $list, 'offset' => $offset_next, 'origin' => $origin),
                "extra" => array('hasmore' => $hasmore),
                )
            );
}

recommend_main();
