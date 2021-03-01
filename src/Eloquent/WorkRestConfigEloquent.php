<?php

namespace Duxingyu\WorkTime\Eloquent;

use Duxingyu\WorkTime\Contracts\WorkIsDoneInterface;
use Duxingyu\WorkTime\Contracts\WorkStartInterface;
use Exception;

class WorkRestConfigEloquent implements WorkIsDoneInterface, WorkStartInterface
{
    protected $startTime;//任务开始时间
    protected $taskTime; //任务完成时间秒
    protected $workTime; //工作时间段
    protected $restTime; //休息时间

    /**
     * 获取工作完成时间秒
     * @return int|void
     */
    final public function getTaskTime()
    {
        $this->taskTime = $this->setTaskTime();
        return $this->taskTime ?: time();
    }

    /**
     * 获取任务开始时间
     * @return int|mixed|void
     */
    final public function getStartTime()
    {
        $this->startTime = $this->setStartTime();
        return $this->startTime ?: time();
    }

    /**
     * 获取每天工作时间
     * @return array
     * @throws Exception
     */
    final public function getWorkTime()
    {
        $this->workTime = $this->setWorkTime();
        if (!$this->workTime || empty($this->workTime)) {
            throw new Exception('请设置工作时间');
        }
        return $this->workTime;
    }

    /**
     * 获取休息时间
     * @return array
     */
    final public function getRestTime()
    {
        $this->restTime = $this->setRestTime();
        return $this->workTime;
    }

    /**
     * 设置每天上班时间段
     * @return array  [
     *      [
     *         'start_time'=>'08:00', //上班时间
     *         'end_time'=>'12:30',//下班时间
     *      ],
     *      [
     *         'start_time'=>'14:00',//上班时间
     *         'end_time'=>'18:30',//下班时间
     *      ]
     * ]
     */
     public function setWorkTime(){

     }

    /**
     * 设置休息时间
     * @return array
     * [
     *      '0101'=>1,//日期
     * ]
     */
     public function setRestTime(){

     }

    public function setTaskTime()
    {
        // TODO: Implement setTaskTime() method.
    }

    public function setStartTime()
    {
        // TODO: Implement setStartTime() method.
    }
}
