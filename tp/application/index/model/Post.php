<?php
namespace app\index\model;

use think\Model;

class Post extends Model
{
	protected $name = 'post';

	//是否被当前用户点过赞
	public $is_like;

	public function date_convert()
	{
		return date('Y-m-d H:i:s', $this->timestamp);
	}
}

?>