<?php
namespace app\index\controller;

/**
* 
*/
class Blog //extends AnotherClass
{
	
	// function __construct(argument)
	// {
	// 	# code...
	// }
	
	public function get($id)
	{
		return '查看id='.$id.'的内容';
	}

	public function read($name)
	{
		return '查看name='.$name.'的内容';
	}

	public function archive($year, $month)
	{
		return '查看'.$year.'/'.$month.'的归档内容';
	}

	public function test($t='eat shit')
	{
		return '这：'.$t.',只是一个酷酷的test！';
	}
}



?>