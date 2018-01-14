<?php
namespace app\index\controller;

use think\Controller;
use think\Db;
use think\Request;
use think\Session;
use think\Validate;
use app\index\model\User as UserModel;
use app\index\model\Post as PostModel;
use app\index\model\Like as LikeModel;
use app\index\model\Comments as CommentsModel;

class Index extends Controller
{   
    public function index()
    {   
        //默认渲染自己的标签
        $assign = $this->post_arg($tag='My');
        $this->assign($assign);

        return $this->fetch();
    }

    //判断用户是否登录
    public function get_session()
    {   
        return Session::get('user_data');
    }

    //返回渲染数据的数组，不同标签返回的数据不一样
    //默认展示所有文章的标签
    public function post_arg($tag='All',$page=1)
    {   
        //每页行数
        $page_per_row = 10;
        //要取的哪几行数据
        $rows = ($page-1)*$page_per_row.','.$page_per_row;

        //要渲染的参数
        $assign = [
            'body'      => false,  //所有发布过的文章的主体
            'user_data' => ['nickname' => 'Stranger', 'status' => 0],
            'login'     => false,
            'page'      => false,
        ];

        $user_data = $this->get_session();
        if (isset($user_data)){
            //告知页面用户已登录
            $assign['login']     = true;
            //赋值用户信息
            $assign['user_data'] = $user_data;

            //看看是切换到看哪组心情的标签
            if($tag == 'My'){
                //该用户所有发布过的心情 
                $assign['body'] = PostModel::where(['author' => $user_data['email']])
                                            ->order('timestamp desc')
                                            ->paginate($page_per_row,false,['type'=>'Ajaxbootstrap','page'=>$page]);
            }elseif ($tag == 'Followings') {
                # code...
                //先找出该用户关注的所有对象email
                $following_list = Db::name('followings')->
                                    where(['following'=>$user_data['email']])->
                                    field('be_followed')->
                                    select();
                //把好友变成数组
                $temp = [];
                foreach ($following_list as $key => $value) {
                    $temp[] = $value['be_followed'];
                }

                $assign['body'] = PostModel::where(['author'=>['in',$temp],'hidden'=>['=', 0]])       
                                    ->order('timestamp desc')
                                    ->paginate($page_per_row,false,['type'=>'Ajaxbootstrap','page'=>$page]);
            }else{
                //列出所有文章出来，根据发布时间倒序排列
                $assign['body'] = PostModel::where('hidden', '=', 0)
                                    ->order('timestamp desc')
                                    ->paginate($page_per_row,false,['type'=>'Ajaxbootstrap','page'=>$page]);
            }
            //这里是把所有文章的id拿出来去数据库匹配，如果发现这个作者赞过这篇文章
            //的话设置一个变量为true来标识文章被这哥们赞过了
            //但是这样做循环遍历着实糟糕，暂时还没想到合适的方法解决，暂时想过外键？
            foreach ($assign['body'] as $key => $value) {
                $query = [
                    'post_id'=>$value['id'],
                    'liked_email'=>$user_data['email'],  //当前用户
                ];

                //文章是否已经被赞过
                $is_like = LikeModel::get($query);
                if(isset($is_like)){
                    $value->is_like = true;
                }
                $value['timestamp'] = date('Y-m-d H:i:s', $value['timestamp']);
            }
        }
        //获得要渲染的数据
        return $assign;
    }

    //发表心情
    public function blog()
    {   
        $session = $this->get_session();
        if (!isset($session)) {
            return $this->error('请先登入！', url('/user/login_form'));
        }

        $email = $session['email'];
        $username = $session['nickname'];
        $pic_path = $session['pic_path'];

        //如果发布评论    
        $body = request()->param('body');
        if(isset($body)){
            $post = new PostModel;
            $data = [
                'body'      => $body,
                'author'    => $email,
                'timestamp' => time(),
                'pic_path'  => $pic_path,
            ];

            //输入的文字最多100个
            $validate = new Validate(
                ['body' => 'require|max:150'],
                ['body.max'=>'字数不能超过150字']
            );
            if (!$validate->check($data)) {
                return $this->error($validate->getError());
            }

            if($post->save($data)){
                return $this->success('发布成功！', url('/'));
            }else{
                return $this->error($post->getError());
            }
        }

        $this->assign('user_data', $session);
        return $this->fetch();
    }

    public function like()
    {   
        
        $request = input('post.');

        if(empty($request)){
            return ['name'=>'bob'];
        }else{

            $query = [
            'liked_email' => $request['email'],
            'post_id' => $request['id'],
            ];

            // 取think_like表内 该角色是否对该文章点赞的记录
            $liked = LikeModel::get($query);

            if($liked){
                //返回的array
                $respones = ['is_like' => 1];
            }else{
                //创建数据
                $like = new LikeModel;
                $like->save($query);

                $post = PostModel::get($request['id']);
                //获取心情的点赞数量
                $liked_count = $post['liked'];
                //点了赞以后的赞数量
                $updata_liked_count = $liked_count + 1;

                $temp = ['liked' => $updata_liked_count];
                //更新点赞数量
                $post->save($temp);
                $respones = ['liked_count'=>$updata_liked_count, 'is_like' => 0];
            }
            return $respones;
        }
    }

    public function delete()
    {
        $request = input('post.');
        
        if(empty($request)){
            return $this->error('请求不成功！', url('index/index'));
        }else{
           $query = [
                'id' => $request['post_id']
            ];
            //用户要删除的那条post
            $post = PostModel::get($query);
            if($post){
                //post存在就删了
                $post->delete();
                return $this->redirect(url('/'));
            }else{
                //否则报个错先
                return $this->error($post->getError());
            } 
        }
    }

    //评论
    public function comments($id='')
    {   
        $session = $this->get_session();

        $assign = [
            'post'      => false,
            'comments'  => false,
            'user_data'  => $session,
        ];

        $post_id = $id;

        $post = PostModel::get(['id'=>$post_id]);
        $assign['post'] = $post;

        $_post = input('post.');
        if(!empty($_post)){
            $comment_author = $session['email'];

            $data = [
                'post_id'        => $post_id ,
                'comment_author' => $comment_author,
                'comment_body'   => $_post['body'],
                'timestamp'      => time(),
                'pic_path'       => $session['pic_path'],
            ];

            //防止数据重复提交
            if(!Index::checkToken($_post['TOKEN'])){
                $this->error('请不要重复提交页面');
            };

            $comment = new CommentsModel;
            $comment->save($data);

            $post->save(['comments'=>$post['comments']+1]);
        }

        $comments = CommentsModel::where(['post_id' => $post_id])->paginate();
        $assign['comments'] = $comments;

        //生成一个TOKEN
        Index::createToken();

        $this->assign($assign);
        return $this->fetch();
    }   

    /* 加密TOKEN */
    static function authcode($str) {
        $key = "a31dFedSDA*@93#*$)12#($)#*%^!@#afebxzdfgwy$25";
        $str = substr(md5($str), 8, 10);
        return md5($key.$str);
    }

    //创建TOKEN
    static function createToken(){
       $code = chr(mt_rand(0xB0, 0xF7)).chr(mt_rand(0xA1, 0xFE)).chr(mt_rand(0xB0, 0xF7)).chr(mt_rand(0xA1, 0xFE)).chr(mt_rand(0xB0, 0xF7)).chr(mt_rand(0xA1, 0xFE));
       session('TOKEN', Index::authcode($code));
    }

    //判断TOKEN
    static function checkToken($token) {
        if ($token == session('TOKEN')){
            session('TOKEN', NULL);
            return true;
        }else{
            return false;
        }
    }

    //ajax局部刷新不同标签的数据
    public function change_tag()
    {   
        //这里有个坑，这里是传了个数组进来了，直接取'tag'是取不到的
        $tag  = request('post')->param('tag');
        //第几页的数据
        $page = request('post')->param('pages');
        //获得该标签的数据
        $assign = $this->post_arg($tag=$tag, $page=(int)$page);

        $this->assign($assign);

        if(request()->isAjax()){ 
            return $this->fetch();
            // return dump(request('post'));
        }
    }

    //该条心情隐藏
    public function hide($post_id)
    {
        $post = PostModel::get(['id' => $post_id]);
        //心情设置隐藏
        return $post->save(['hidden'=> 1]);
    }

    //该条心情显示
    public function show($post_id)
    {
        $post = PostModel::get(['id' => $post_id]);
        //心情设置显示
        return $post->save(['hidden'=> 0]);
    }

    //ajax隐藏心情
    public function hide_post()
    {
        $id   = request()->param('id');
        $type = request()->param('type');

        if ($type == 'show') {
            $this->show($id);
        }elseif ($type == 'hide') {
            $this->hide($id);
        }
    }

    public function test()
    {   

        $t = PostModel::where(['author' => 'Yan@god.com'])
                        ->paginate();
        foreach ($t as $key => $value) {
            echo $key.'===='.$value->is_like;
            $value->is_like = 0;
            echo '<br/>';
        }
    }

    public function test1()
    {
        $page = request()->param('apage');
        return ['p' => $page];
        if (!empty($page)) {
            $rel = Db::table('think_post')->paginate(5,false,[
                'type'     => 'Bootstrap',
                'var_page' => 'page',
                'page' => $page,
                'path'=>'javascript:AjaxPage([PAGE]);',

            ]);
             $page = $rel->render();
          }
      return ['list'=>$rel,'page'=>$page];
    }

}
