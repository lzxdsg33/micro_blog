<?php
namespace app\index\validate;

use think\Validate;

class User extends Validate
{

	protected $rule = [
		['nickname', 'require|min:5'],
		['email','require|email', '需要正确的email地址'],
		
		// ['birthday','dateFormat:Y-m-d','日期格式']
	];
}




?>