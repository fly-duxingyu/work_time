<?php

namespace Duxingyu\WorkTime\Eloquent;

use module\cases\abase\enum\CaseStageEnum;
use module\caseSetting\stage\server\CaseStageServer;
use module\caseSetting\workTime\server\WorkTimeServer;

class WorkEndTimeCalculate
{
    public $configEloquent;

    public function __construct(WorkRestConfigEloquent $configEloquent)
    {
        $this->configEloquent = $configEloquent;
    }
    /**
     * 是否是周末休息日
     * @param $day
     * @return bool
     */
    private function checkIsWeekend($day)
    {
        $weak = date("N", $day);
        return in_array($weak, array(6, 7)) ? true : false;
    }

    /**
     * 获取节假日json文件判断是否是休息日
     * @param $day_time_stamp
     * @return bool|null  //0调休(工作日) 1休息 2法定休息
     * @throws CException
     */
    private function getDayRestJsonFiles($day_time_stamp)
    {
        $year = date('Y', $day_time_stamp);
        $day = date('md', $day_time_stamp);
        $year_json = file_get_contents(CC::app()->request->getFilePath('/files/' . $year . '_data.json'));
        $year_array = $year_json ? @json_decode($year_json, true) : [];
        if (!isset($year_array[$day])) {
            return null;
        }
        return $year_array[$day] ? true : false;
    }

    /**
     * 是否是工作日
     * @param $start_time
     * @return bool
     * @throws CException
     */
    private function getIsDayRest($start_time)
    {
        //是否是周末
        $is_weekend = $this->checkIsWeekend($start_time);
        //是否是休息日
        $day_rest = $this->getDayRestJsonFiles($start_time);

        //$day_rest为null时以$is_weekend判断为准
        if ($is_weekend && is_null($day_rest)) {
            return true;
        }
        //$day_rest为false时以$day_rest判断为准
        if ($is_weekend && !$day_rest) {
            return false;
        }
        return $day_rest ? true : false;
    }

    /**
     * 节假日是否为休息日  和  一个工作日的时间秒数
     * @param $dept_type
     * @return array
     */
    private function workTimeHour($dept_type)
    {
        $workTime = WorkTimeServer::getWorkTimeHour($dept_type);
        $workTimeStamp = [];
        $workTotalTimeStamp = 0;
        $is_holiday = $workTime['is_holiday'];
        if ($workTime) {
            foreach ($workTime['content'] as $item) {
                $end_time = strtotime($item['end_hour']);
                $start_time = strtotime($item['start_hour']);
                $workTimeStamp[$start_time]['timeStamp'] = ($end_time - $start_time);
                $workTimeStamp[$start_time]['start_hour'] = $item['start_hour'];
                $workTimeStamp[$start_time]['end_hour'] = $item['end_hour'];
                $workTotalTimeStamp += $workTimeStamp[$start_time]['timeStamp'];
            }
        }
        ksort($workTimeStamp);
        return [$is_holiday, $workTimeStamp, $workTotalTimeStamp];
    }

    /**
     * 获取环节时间
     * @param $name //环节名称
     * @param $workTotalTimeStamp //一个工作日对应的时长秒
     * @return array
     */
    private function getStageTime($name, $workTotalTimeStamp)
    {
        $stage = CaseStageServer::getStageInfo($name);
        if ($name == CaseStageEnum::DISPOSE) {
            $disposeTime = self::$objStage->getDisposeTime();
            $disposeTime = explode('-', $disposeTime);
            if ($disposeTime[1] == 'h') {
                $endTimeInterval = ($disposeTime[0] * 3600);
            } else {
                $endTimeInterval = $disposeTime[0] * $workTotalTimeStamp;
            }
            return [$endTimeInterval, $endTimeInterval * ($stage['stage_threshold'] / 100)];
        }
        return $stage ? [$stage['stage_interval'], $stage['stage_interval'] * ($stage['stage_threshold'] / 100)] : [];
    }

    /**
     * 开始时间调整成最近的上班开始时间
     * @param $startTimeStamp
     * @param $workTimeStamp
     * @return false|int
     */
    private function startWorkTime($startTimeStamp, $workTimeStamp)
    {
        $prefix = date('Y-m-d', $startTimeStamp);
        $tomorrow = false;
        foreach ($workTimeStamp as $key => $item) {
            $tomorrow = false;
            $end_time = strtotime($prefix . ' ' . $item['end_hour']);
            $start_time = strtotime($prefix . ' ' . $item['start_hour']);

            $end_time_array[] = $end_time;
            //小于开始时间
            if ($startTimeStamp <= $start_time) {
                $startTimeStamp = $start_time;
                break;
            }
            //小于开始时间
            if ($startTimeStamp > $start_time && $startTimeStamp < $end_time) {
                break;
            }
            if ($startTimeStamp >= max($end_time_array)) {
                $tomorrow = true;
            }
        }
        $startTimeStamp = $tomorrow ? strtotime("+1 day", strtotime($prefix . ' ' . reset($workTimeStamp)['start_hour'])) : $startTimeStamp;
        return $startTimeStamp;
    }

    /**
     * 计算截止时间
     * @param $workTimeStamp
     * @param $endTimeStamp
     * @param $prefix
     * @param $diff
     * @return array
     */
    private function computationTime($workTimeStamp, $endTimeStamp, $prefix, $diff)
    {
        $isCirculation = false;
        foreach ($workTimeStamp as $key => $item) {
            $end_time = strtotime($prefix . ' ' . $item['end_hour']);
            $start_time = strtotime($prefix . ' ' . $item['start_hour']);
            if ($endTimeStamp >= $start_time && $endTimeStamp < $end_time) {
                $isCirculation = false;
                //下班时间减去环节时间
                $endTimeStamp = ($endTimeStamp + $diff);
                $diff = $end_time - $endTimeStamp;
                //正数就是截止时间就是$endTimeStamp
                if ($diff >= 0) {
                    break;
                } else {
                    //负数的话证明下班时间小于截止时间，需要再次循环
                    $diff = abs($diff);
                    $endTimeStamp = $end_time;
                    $isCirculation = true;
                }
            } else {
                $isCirculation = true;
            }
        }
        if ($isCirculation) {
            $endTimeStamp = strtotime("+1 day", strtotime($prefix . ' ' . reset($workTimeStamp)['start_hour']));
            $prefix = date('Y-m-d', $endTimeStamp);
        }
        return [$isCirculation, $endTimeStamp, $diff, $prefix];
    }

    private function getDay($start_time, $end_time)
    {
        $day = 0;
        $dt_start = strtotime(date('Y-m-d', $start_time));
        $dt_end = strtotime(date('Y-m-d', $end_time));
        while ($dt_start <= $dt_end) {
            $isRest = $this->getIsDayRest($dt_start);
            if ($isRest) {
                $day += 1;
            }
            $dt_start = strtotime('+1 day', $dt_start);
        }
        return $day;
    }

    /**
     * @param $stageTimeStamp //环节时间秒
     * @param $workTotalTimeStamp //每天工作时间秒
     * @param $start_time //开始工作的时间
     * @param $workTimeStamp //每天工作时间数组
     * @param $is_holiday //节假日是否休息
     * @return false|int|mixed
     */
    private function getEndTime($stageTimeStamp, $workTotalTimeStamp, $start_time, $workTimeStamp, $is_holiday)
    {
        $stage_day = floor($stageTimeStamp / $workTotalTimeStamp);//环节截止时间天数  一天=一个工作日的时间
        $diff = $stageTimeStamp - ($stage_day * $workTotalTimeStamp);//剩余秒数
        $endTimeStamp = $start_time + ($stage_day * 86400);//环节结束时间戳
        $prefix = date('Y-m-d', $endTimeStamp);
        do {
            list($isCirculation, $endTimeStamp, $diff, $prefix) = $this->computationTime($workTimeStamp, $endTimeStamp, $prefix, $diff);
        } while ($isCirculation);
        if ($is_holiday) {
            //节假日是休息日
            do {
                $day = $this->getDay($start_time, $endTimeStamp);
                if ($day) {
                    $start_time = strtotime("+1 day", $endTimeStamp);

                    $endTimeStamp = strtotime("+$day day", $endTimeStamp);
                }
            } while ($day);
        }
        return $endTimeStamp;
    }

    /**
     * 返回时间
     * @return mixed
     */
    public function stageCalculate()
    {
        //获取工作时间
        list($is_holiday, $workTimeStamp, $workTotalTimeStamp) = $this->workTimeHour(self::$objStage->getDeptType());
        //获取环节时间
        $stageArray = $this->getStageTime(self::$objStage->getStageName(), $workTotalTimeStamp);
        $start_time = $this->startWorkTime(self::$objStage->getStartTime(), $workTimeStamp);
        $stageEndTimeStamp['end_time'] = $this->getEndTime($stageArray[0], $workTotalTimeStamp, $start_time, $workTimeStamp, $is_holiday);
        $stageEndTimeStamp['yellow_time'] = $this->getEndTime($stageArray[1], $workTotalTimeStamp, $start_time, $workTimeStamp, $is_holiday);

        return $stageEndTimeStamp;

    }
}
