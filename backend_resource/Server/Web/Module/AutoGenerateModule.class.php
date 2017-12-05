<?php

/**
 * @name eolinker open source，eolinker开源版本
 * @link https://www.eolinker.com
 * @package eolinker
 * @author www.eolinker.com 广州银云信息科技有限公司 ©2015-2016
 *  * eolinker，业内领先的Api接口管理及测试平台，为您提供最专业便捷的在线接口管理、测试、维护以及各类性能测试方案，帮助您高效开发、安全协作。
 * 如在使用的过程中有任何问题，欢迎加入用户讨论群进行反馈，我们将会以最快的速度，最好的服务态度为您解决问题。
 * 用户讨论QQ群：284421832
 *
 * 注意！eolinker开源版本仅供用户下载试用、学习和交流，禁止“一切公开使用于商业用途”或者“以eolinker开源版本为基础而开发的二次版本”在互联网上流通。
 * 注意！一经发现，我们将立刻启用法律程序进行维权。
 * 再次感谢您的使用，希望我们能够共同维护国内的互联网开源文明和正常商业秩序。
 *
 */
class AutoGenerateModule
{
    /**
     * 导入接口
     * @param $data
     * @param $project_id
     * @param $user_id
     * @return bool
     */
    public function importApi(&$data, &$project_id, &$user_id)
    {
        $dao = new AutoGenerateDao();
        $result = $dao->importApi($data, $project_id);
        if ($result) {
            $log_dao = new ProjectLogDao();
            $log_dao->addOperationLog($project_id, $user_id, ProjectLogDao::$OP_TARGET_PROJECT, $project_id, ProjectLogDao::$OP_TYPE_UPDATE, '通过自动生成文档功能更新接口文档', date('Y-m-d H:i:s', time()));
            return $result;
        } else {
            return FALSE;
        }
    }

    /**
     * 检查项目权限
     * @param $user_name
     * @param $user_password
     * @param $project_id
     * @return bool|array
     */
    public function checkProjectPermission(&$user_name, &$user_password, &$project_id)
    {
        $dao = new GuestDao;
        $user_info = $dao->getLoginInfo($user_name);
        if (md5($user_password) == $user_info['userPassword']) {
            $project_dao = new ProjectDao();
            if ($project_dao->checkProjectPermission($project_id, $user_info['userID'])) {
                $auth_dao = new AuthorizationDao();
                $result = $auth_dao->getProjectUserType($user_info['userID'], $project_id);
                if ($result === FALSE) {
                    return FALSE;
                } elseif ($result > 2) {
                    return FALSE;
                } else {
                    return $user_info;
                }
            } else {
                return FALSE;
            }
        } else {
            return FALSE;
        }
    }
}