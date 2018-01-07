<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2016 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

return [
    '__pattern__' => [
        'name' => '\w+',
        'id' => '\d+',
        'year' => '\d{4}',
        'month' => '\d{2}',
    ],

    // 'index' => 'index/index/show_posts',
    'blog' => 'index/index/blog',
    'index/like' => 'index/index/like',
    'index/test' => 'index/index/test',
    'index/delete' => 'index/index/delete',
    // 'show_posts/tag/:tag' => 'index/index/show_posts',
    // 'show_posts' => 'index/index/show_posts',
    'comments/id/:id'   => 'index/index/comments',
    'index/change_tag/[:page]'   => 'index/index/change_tag',
    'test' => 'index/index/test',


    'user/index' => 'index/user/index',
    'user/create' => 'index/user/create',
    'user/add' => 'index/user/add',
    'user/add_list' => 'index/user/addList',
    'user/update/:id' => 'index/user/update',
    'user/delete/:id' => 'index/user/delete',
    // 'user/:id' => 'index/user/read',
    // 'user/:nickname' => 'index/user/read',
    'user/login' => 'index/user/login',
    'user/logout' => 'index/user/logout',
    'user/login_form' => 'index/user/login_form',
    'user/update' => 'index/user/update',
    'user/user_login_status' => 'index/user/user_login_status',
    'user/friends' => 'index/user/friends',
    'user/add_friends' => 'index/user/add_friends',
    'user/friend_address/[:email]' => 'index/user/friend_address',
    'user/test' => 'index/user/is_regist',
];	
