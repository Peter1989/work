<?php
require_once('recommend.php');
require_once('groups_common.php');

//通过语言来推荐群组的入口
function recommend_by_language($inner_call = false, $page = 1, $offset = 0, $size = 15, $origin = 0){
    $uid = $_REQUEST['me'];
    $lang = get_lang($uid);
    $recommend = get_recommend($lang);
    list($gids,$hasmore,$offset_next, $origin) = get_gids($recommend, $page, $offset, $size, $origin);

    $groups = groups_mget($gids);
    foreach($groups as $k => $v){
        if(!empty($v) && isset($v['gid'])){
            $list[] = groups_format($v, $uid, $inner_call);
        }
    }

    if($inner_call){
        return $list;
    }else{
        return array($list, $offset_next, $hasmore, $origin);
    }
}

//获取登陆者的语言
function get_lang($uid){
    $redis = redis_get_instance('USERS',$uid);
    $lang = $redis->HGET("u:$uid",'lang');
    $lang = strtoupper($lang);
    $lang = str_replace('-','_',$lang);
    return $lang;
}

//根据语言获取推荐群组
function get_recommend($lang){
    $hit = isset(RecommendGroups::$$lang);
    if($hit){
        $recommend = RecommendGroups::$$lang;
    }else{
        $recommend = RecommendGroups::$EN_US;
    }
    return $recommend;
}

//根据推荐群组和偏移量获取gids
function get_gids($recommend, $page, $offset, $size, $origin){

    $hasmore = 1;
    //推荐群的总个数sum，推荐群的最大索引。
    $sum = count($recommend);
    $top = $sum -1;
    $offset_next = 0;

    if($page === 1){
        //如果推荐群的总个数小于size个数，一次返回，不能翻页
        if($sum <= $size){
            $hasmore = 0;
            return array($recommend, $hasmore, $offset_next, $origin);
        }
        $start = rand(0, $top);
        //给客户端标识第一次取的位置
        $origin = $start;
        $offset_next = $start + $size;
        //如果推荐群的总个数大于size个数，那么从随机数start开始取size个。
        $gids = get_gids_from_offset($start, $size, $recommend, $sum);
    }else{

        //位移量大于最大索引数的时候。
        if($offset > $top){
            $offset = $offset - $sum;
        }

        if($offset < $origin && $offset + $size > $origin){
            $size = $origin - $offset;
            $hasmore = 0;
            $gids = get_gids_from_offset($offset, $size, $recommend, $sum);
        }else{
            $offset_next = $offset + $size;
            $gids = get_gids_from_offset($offset, $size, $recommend, $sum);
        }
    }
    return array($gids, $hasmore, $offset_next, $origin);
}

//根据起始位置、每个群数、推荐群组、和推荐的群的个数获取gid
function get_gids_from_offset($offset, $size, $recommend, $sum){
    //位移量加size如果大于总个数需要叠加推荐群组
    if($offset + $size > $sum){
        $recommend = array_merge($recommend, $recommend);
    }
    $gids = array_slice($recommend, $offset, $size);
    return $gids;
}

function groups_format($info, $me, $inner_call){
    $redis = redis_get_instance('USERS', 0);
    $ret = $redis->multi(Redis::PIPELINE)
                ->ZCARD("g:{$info['gid']}:members:active") 
                ->ZSCORE("g:{$info['gid']}:members:active", $me)
                ->EXEC();

    $group = array();
    $group['groups_name'] = $info['name'];
    $group['groups_avatar'] = $info['avatar'];
    $group['groups_gid'] = intval($info['gid']);
    $group['groups_city'] = $info['city'];
    $group['groups_members_count'] = intval($ret[0]);
    $group['groups_in_members'] = $ret[1] ? 1 : 0;
    //如果是单独请求的页面加上群描述
    if($inner_call == 0){
        $group['groups_description'] = $info['description'];
    }
    return $group;
}

