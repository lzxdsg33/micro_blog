<?php
namespace app\index\controller;

use app\index\model\User as UserModel;
use app\index\model\Following as FollowingModel;
use think\Controller;
use think\Validate;
use think\Session;
use think\Db;

class User extends Controller
{

	public function index()
	{	
		$list = UserModel::paginate(4);
		$this->assign('list', $list);
		return $this->fetch();
	}

	//注册页面
	public function create()
	{
		return view();
	}

	//注册用户
	public function add()
	{	
		$user = new UserModel;
		//数据库字段为int类型，现在日期是str，暂时先这样处理，到时候做成下拉框
		// $date_to_int = (int)strtotime(input('birthday'));
		$input = input('post.');
 		$data = [
 			'nickname' => $input['nickname'],
 			'password' => $input['password'],
 			'email'    => $input['email'],
  		];

		$nickname = UserModel::get(['nickname'=>$data['nickname']]);
        $email =  UserModel::get(['email'=>$data['email']]);
        //判断是否已经有同名或同email的用户注册
        if ($nickname){
        	 return $this->error('昵称已存在');
    	}elseif ($email) {
    		return $this->error('Email已存在');
    	}

		//验证规则
		$rule = [
			['nickname', 'require|min:5'],
			['password','/^(?![0-9]+$)(?![a-zA-Z]+$)[0-9A-Za-z]{6,50}$/',
			'密码必须是6-15位之间的数字加字母'],
			['email','require|email', '需要正确的email地址']
		];

		//验证器，主要验证用户输入的密码规则
		$validate = new Validate($rule);
		if(!$validate->check($data)){
			return $this->error($validate->getError());
		}

		//密码md5加密入库
		$data['password'] = UserModel::convert_password($data['password']);
		$login_name = $data['nickname'];
		//用户注册时间为当前时间戳，同时更新最后登入时间和注册时间一致
		$data['create_time'] = time();
		$data['update_time'] = time();
		//把生日转换成时间戳
		$temp = $input['year'].'-'.$input['month'].'-'.$input['day'];
		$data['birthday'] 	 = (int)strtotime($temp);
		//用户默认头像地址
		$data['pic_path']    = 'acquiescence.jpeg';
		if($user->save($data)){
			$this->success('注册成功！', url('/'));
		}else{
			$this->error($user->getError());
		}
	}

	//跳转进入用户登录界面
	public function login_form()
	{	
		return $this->fetch('user/login');
	}

	//用户登录操作
	public function login()
	{	
		$user_email = input('post.')['email'];
		$user_password = input('post.')['password'];

		//如果用户输入的邮件地址或者密码不能为空
		if (empty($user_email) or empty($user_password)){
			$this->error('邮件地址或者密码不得为空');
		}
		
		//用email取数据库里的用户，没有就返回
		$user = UserModel::get(['email'=>$user_email]);
		if (empty($user)){
			$this->error('该邮件地址不存在或邮件地址输入错误');
		}

		//转换下密码，md5的密码和数据库里的对应下
		$converted_password = $user->convert_password($user_password);
		if ($converted_password == $user['password']){

			// 最后登入日期的时间戳
			$user->save(['update_time'=>time(),'status'=>1]);
			//添加session
			Session::set('user_data', $user->to_session());
			
			return $this->success('欢迎回来！', url('/show_posts'));
		}else{
			return $this->error('密码错误，请重试');
		}
	}

	//用户退出登录
	public function logout()
	{	
		$session = Session::get('user_data');
		//设置session,如果已经有session了，先销毁了
		//获取当前用户对象
		$user = UserModel::get(['email'=>$session['email']]);
		//更新下最后登入日期，并且状态为下线
		$user->save(['update_time'=>time(),'status'=>0]);
		//销毁sesison
		Session::destroy();
		//返回首页
		return $this->success('已登出！', url('/'));

	}

	//判断用户是否登录
	public function user_login_status()
	{
		//判断是否有session，有即为登录用户
		if(Session::has('user_data')){
			$session = Session::get('user_data');
			//读取数据库用户资料
			$user = UserModel::get(['email'=>$session['email']]);
			return $this->info($user, true);
		}
		//否则视为未登入用户，让他先登入才能看用户资料
		return $this->error('请先登入！', url('user/login_form'));

	}

	//显示用户信息
	/*
		param:$user  用户对象
		param:$is_me 是否是自己
	 */
	public function info($user, $is_me)
	{	
		//关注者是该用户的关注列表
		$query = ['following' => $user['email']];
		$paginate = FollowingModel::where($query)->paginate();

		//这个地方暂时用join查询出该用户关注的对象和对象的头像地址
		//后面可能会用缓存？或者更好的解决方法
		$query = 
		'select 
		think_followings.be_followed, think_followings.follow_time,think_user.pic_path,
		think_user.profile
		from think_followings join think_user on
		think_followings.be_followed=think_user.email 
		where think_followings.following=(?)';

		$following_list = Db::query($query,[$user['email']]);

		$assign = [
			'user_data'      => $user->to_session(),
			'following_list' => $following_list,
			'paginate'       => $paginate,
			'is_me'          => '',
		];

		$is_me == true ? $assign['is_me'] = true : $assign['is_me'] = false;
		$this->assign($assign);
		return $this->fetch('user/info');
	}

	//用户搜索好友
	public function friends()
    {	
    	if(!Session::has('user_data')){
			return $this->error('请先登入！', url('user/login_form'));
		}
		$username = Session::get('user_data')['nickname'];

    	$post = input('post.');
    	if(!$post){
    		$this->assign(['username'=>$username]);
    		return $this->fetch();
    	}

    	$user = UserModel::get(['email'=>$post['email']]);
    	if($user){
    		return $this->info($user, false);
    	}else{
    		return $this->error('查无此用户！');
    	}
        
    }

    //数据库添加关注数量和被关注数量
    public function update_follow_count($following, $be_followed)
    {
    	$following   = UserModel::get(['email'=>$following]);
    	$be_followed = UserModel::get(['email'=>$be_followed]);

    	$following_count = $following['following'];
    	$fans_count = $be_followed['fans'];

    	$following->save(['following'=>$following_count+1]);
    	$be_followed->save(['fans'=>$fans_count+1]);
    }

    //添加好友 ajax
    public function add_friends()
    {	
    	$request = input('post.');
    	$respons = ['result' => 'Fail!'];

        if(empty($request)){
            return $respons;
        }else{
        	$session = Session::get('user_data');

        	$following = $session['email'];
        	$be_followed = $request['email'];

        	$query = [
        		'following'   => $following,
        		'be_followed' => $be_followed,
        	];

        	//看这两兄弟是不是好友先
        	$friend = FollowingModel::get($query);
        	//这个这两兄弟不是好友的话就提交数据库
        	if(!$friend){
        		//记得加入当前时间入库
        		$query['follow_time'] = date('Y-m-d');
        		// 不是好友就先加一波好友再说
        		$add_friend = new FollowingModel;
        		$add_friend->save($query);

        		//在用户User表里面对粉丝和关注进行添加
        		$this->update_follow_count($following, $be_followed);

        		$respons['result'] = 'Following Success!';
        	}else{
        		$respons['result'] = 'You have been friend!';
        	}

        	return $respons;
        }
    }

    //查找好友的跳转
    public function f_hp($email)
    {
    	$user = UserModel::get(['email'=>$email]);
    	return $this->info($user, false);
    }

	//更新用户资料
	public function update()
	{	
		//先获取登录用户逇session
		$user_data = Session::get('user_data');
		//用户是否修改资料，由表单发起请求
		$update = input('post.');
		if($update){
			//如果更新了，先获取用户的数据库资料
			$user = UserModel::get(['email'=>$user_data['email']]);
			//如果更新了头像
			$file = request()->file('image');
			if($file){
				$info = $file->move(ROOT_PATH . 'public' . DS . 'uploads');
				if($info){
					//图片存放的地址
					$user_data['pic_path'] = $info->getSaveName();
				}else{
					return $file->getError();
				}
			}

			//修改的资料入库
			$temp = ['pic_path'=>$user_data['pic_path'], 'profile'=>$update['about_me']];
			if($user->save($temp)){
				//入库成功则跳转打哦资料页面
				return $this->info($user,true);
			}else{
				return $this->error($user->getError());
			}
		}

		//按原来的数据渲染一波
		$this->assign('user_data', $user_data);
		//没有修改资料就是单纯访问页面
		return $this->fetch();
	}




























	public function addList()
	{
		$user = new UserModel;
		$list = [
			['nickname'=>'Yan', 'email'=>'Yan@god.com', 'birthday'=>strtotime('1993-12-21')],
			['nickname'=>'YanChi', 'email'=>'YanG.@god.com', 'birthday'=>strtotime('1993-11-21')],
			['nickname'=>'Amy', 'email'=>'AmY.@god.com', 'birthday'=>strtotime('1994-12-21')],
			['nickname'=>'Bruce', 'email'=>'Bc.@god.com', 'birthday'=>strtotime('1993-12-16')],
		];
		if ($user->saveAll($list)){
			return '用户添加成功！';
		}else{
			return $user->getError();
		}
	}

	// public function read($id='')
	// {
	// 	$user = UserModel::get($id);
	// 	echo $user['nickname']."<br/>";
	// 	echo $user['email']."<br/>";
	// 	echo date('Y/m/d', $user['birthday'])."<br/>";
	// }

	public function test($key, $value)
	{
		$user = UserModel::get([$key=>$value]);
		echo $user['nickname']."<br/>";
		echo $user['email']."<br/>";
		echo date('Y/m/d', $user['birthday'])."<br/>";
	}



	public function delete($id='')
	{
		$user = UserModel::get($id);
		if ($user){
			$user->delete();
			return '删除成功！';
		}else{
			return '删除的用户不存在！';
		}
	}

	public function all_delete()
	{
		$list = UserModel::all();
		if ($list){
			foreach(UserModel::all() as $user){
				$user->delete();
			}
			return 'Table is clear !';
		}else{
			return 'Table is null !';
		}
	}
}

?>