<?php
namespace app\index\model;

use think\Model;

class User extends Model
{
	protected $name = 'user';

	static public  function convert_password($password)
	{
		return md5($password);
	}

	public  function to_session()
	{
		return [
			'nickname'=>$this->nickname, 
			'email'=>$this->email, 
			'create_time'=>$this->create_time, 
			'update_time'=>$this->update_time, 
			'pic_path'=>$this->pic_path,
			'following'=>$this->following,
			'fans'=>$this->fans,
			'status'=>$this->status,
			'profile'=>$this->profile,
		];
	}
}




?>