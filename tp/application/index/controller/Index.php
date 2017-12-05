<?php
namespace app\index\controller;

use think\Controller;
use think\Db;
use think\Request;
use think\Session;
use app\index\model\User as UserModel;
use app\index\model\Post as PostModel;
use app\index\model\Like as LikeModel;
use app\index\model\Comments as CommentsModel;

class Index extends Controller
{   
    public function index()
    {   
        return $this->show_posts($tag='My');
    }

    public function get_session()
    {   
        //先判断用户是否登录
        if(Session::has('user_data')){
            return Session::get('user_data');
        }

        return $this->error('请先登入！', url('user/login_form'));
    }

    //发表心情
    public function blog()
    {   
        $session = $this->get_session();
        //如果发布评论    
        $body = request()->param('body');
        $email = $session['email'];
        $username = $session['nickname'];

        if($body){
            $post = new PostModel;
            $data = [
                'body'      => $body,
                'author'    => $email,
                'timestamp' => time(),
                'pic_path'  => $session['pic_path'],
            ];

            if($post->save($data)){
                return $this->success('发布成功！', url('/'));
            }else{
                return $this->error($post->getError());
            }
        }
        $this->assign('username', $username);
        return $this->fetch();
    }

    public function show_posts($tag='')
    {   
        //要渲染的参数
        $assign = [
            'body'      => false,
            'user_data' => ['nickname' => 'Stranger', 'status' => 0],
        ];

        $user_data = Session::get('user_data');
        if ($user_data){
            //显示发布过的心情
            $post = PostModel::get(['author'=>$user_data['email']]);
            $assign['user_data'] = $user_data;

            if($post){
                //看看是切换到看哪组心情的标签
                if($tag=='My'){
                    //该作者的所有发布过的心情
                    $assign['body'] = PostModel::where(['author' => $user_data['email']])->paginate();
                }else{
                    //先找出该用户关注的所有对象email
                    $following_list = Db::name('followings')->where(['following'=>$user_data['email']])->field('be_followed')->select();
                    //把好友变成数组
                    $temp = [];
                    foreach ($following_list as $key => $value) {
                        $temp[] = $value['be_followed'];
                    }
                    $assign['body'] = PostModel::where(['author'=>['in',$temp]])->paginate();
                }

                foreach ($assign['body'] as $key => $value) {
                    $query = [
                        'post_id'=>$value['id'],
                        'liked_email'=>$value['author']
                    ];

                    //文章是否已经被赞过
                    $is_like = LikeModel::get($query);
                    $value['is_like'] = false;
                    if($is_like){
                        $value['is_like'] = true;
                    }
                    
                    $value['timestamp'] = date('Y-m-d H:i:s', $value['timestamp']);
                    $value['pic_path'] = UserModel::get(['email'=>$value['author']])['pic_path'];
                }
            }
        } 

        $this->assign($assign);
        return $this->fetch('index/index');
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
                return $this->success('删除成功！');
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
            'username'  => $session['nickname'],
        ];

        $post_id = $id;

        $post = PostModel::get(['id'=>$post_id]);
        $assign['post'] = $post;

        $_post = input('post.');
        if($_post){
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

    public function test()
    {
        $query = 
        'select think_user.email,think_user.pic_path
        from think_user left join think_post  on
        think_post.author=think_user.email;';

        $test = Db::query($query);
        dump($test);
        // foreach ($test as $key => $value) {
        //     $user = PostModel::get(['author'=>$value['author']]);
        //     $user->save(['pic_path'=>$value['pic_path']]);
        // }
    }
}
