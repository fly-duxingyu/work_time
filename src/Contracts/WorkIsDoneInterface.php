<?php


namespace Duxingyu\WorkTime\Contracts;


interface WorkIsDoneInterface
{
    /**
     * 设置任务完成时间秒
     * @return int
     */
    public function setTaskTime();

}
