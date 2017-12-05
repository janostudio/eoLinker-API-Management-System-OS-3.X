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
class ProjectDao
{

    /**
     * 创建项目
     *
     * @param $projectName string
     *            项目名
     * @param $projectType int
     *            项目类型 [0/1/2/3]=>[Web/App/PC/其他]
     * @param $projectVersion string
     *            项目版本，默认为1.0
     * @param $userID int
     *            用户ID
     * @return bool|array
     */
    public function addProject(&$projectName, &$projectType, &$projectVersion, &$userID)
    {
        // 获取数据库
        $db = getDatabase();

        $db->beginTransaction();

        // 插入项目
        $db->prepareExecute('INSERT INTO eo_project(eo_project.projectName,eo_project.projectType,eo_project.projectVersion,eo_project.projectUpdateTime) VALUES (?,?,?,?);', array(
            $projectName,
            $projectType,
            $projectVersion,
            date('Y-m-d H:i:s', time())
        ));

        if ($db->getAffectRow() < 1) {
            $db->rollback();
            return FALSE;
        }

        $projectID = $db->getLastInsertID();

        // 生成项目与用户的联系
        $db->prepareExecute('INSERT INTO eo_conn_project (eo_conn_project.projectID,eo_conn_project.userID) VALUES (?,?);', array(
            $projectID,
            $userID
        ));
        if ($db->getAffectRow() > 0) {
            $db->commit();

            return array(
                'projectID' => $projectID,
                'projectType' => $projectType,
                'projectUpdateTime' => date("Y-m-d H:i:s", time()),
                'projectVersion' => $projectVersion
            );
        } else {
            $db->rollback();
            return FALSE;
        }
    }

    /**
     * 判断项目和用户是否匹配
     *
     * @param $projectID int
     *            项目ID
     * @param $userID int
     *            用户ID
     * @return mixed
     */
    public function checkProjectPermission(&$projectID, &$userID)
    {
        $db = getDatabase();
        $result = $db->prepareExecute('SELECT projectID FROM `eo_conn_project` WHERE projectID = ? AND userID = ?;', array(
            $projectID,
            $userID
        ));

        if (empty($result))
            return FALSE;
        else
            return $result['projectID'];
    }

    /**
     * 删除项目
     *
     * @param $projectID int
     *            项目ID
     * @return bool
     */
    public function deleteProject(&$projectID)
    {
        $db = getDatabase();
        $db->beginTransaction();

        $db->prepareExecute('DELETE FROM eo_project WHERE eo_project.projectID = ?', array(
            $projectID
        ));

        if ($db->getAffectRow() < 1) {
            $db->rollback();
            return FALSE;
        }

        $db->prepareExecute('DELETE FROM eo_conn_project WHERE eo_conn_project.projectID = ? AND eo_conn_project.userType = 0;', array(
            $projectID
        ));

        if ($db->getAffectRow() < 1) {
            $db->rollback();
            return FALSE;
        }

        $db->prepareExecuteAll('DELETE FROM eo_api_group WHERE eo_api_group.projectID = ?;', array($projectID));
        $db->prepareExecuteAll('DELETE FROM eo_api_header WHERE eo_api_header.apiID IN (SELECT eo_api.apiID FROM eo_api WHERE eo_api.projectID = ?);', array($projectID));
        $db->prepareExecuteAll('DELETE FROM eo_api_request_value WHERE eo_api_request_value.paramID IN (SELECT eo_api_request_param.paramID FROM eo_api_request_param LEFT JOIN eo_api ON eo_api_request_param.apiID = eo_api.apiID WHERE eo_api.projectID = ?);', array($projectID));
        $db->prepareExecuteAll('DELETE FROM eo_api_request_param WHERE eo_api_request_param.apiID IN (SELECT eo_api.apiID FROM eo_api WHERE eo_api.projectID = ?)', array($projectID));
        $db->prepareExecuteAll('DELETE FROM eo_api_result_value WHERE eo_api_result_value.paramID IN (SELECT eo_api_result_param.paramID FROM eo_api_result_param LEFT JOIN eo_api ON eo_api_result_param.apiID = eo_api.apiID WHERE eo_api.projectID = ?);', array($projectID));
        $db->prepareExecuteAll('DELETE FROM eo_api_result_param WHERE eo_api_result_param.apiID IN (SELECT eo_api.apiID FROM eo_api WHERE eo_api.projectID = ?)', array($projectID));
        $db->prepareExecuteAll('DELETE FROM eo_api_group WHERE eo_api_group.projectID = ?;', array($projectID));
        $db->prepareExecuteAll('DELETE FROM eo_api WHERE eo_api.projectID = ?;', array($projectID));
        $db->prepareExecuteAll('DELETE FROM eo_api_cache WHERE eo_api_cache.projectID = ?;', array($projectID));

        $db->commit();
        return TRUE;
    }

    /**
     * 获取项目列表
     *
     * @param $userID int
     *            用户ID
     * @param $projectType int
     *            项目类型[-1/0/1/2/3]=>[全部/Web/App/PC/其他]
     * @return bool|array
     */
    public function getProjectList(&$userID, &$projectType = -1)
    {
        $db = getDatabase();

        if ($projectType < 0) {
            $result = $db->prepareExecuteAll("SELECT eo_project.projectID,eo_project.projectName,eo_project.projectType,eo_project.projectUpdateTime,eo_project.projectVersion,eo_conn_project.userType FROM eo_project INNER JOIN eo_conn_project ON eo_project.projectID = eo_conn_project.projectID WHERE eo_conn_project.userID=? ORDER BY eo_project.projectUpdateTime DESC;", array(
                $userID
            ));
        } else {
            $result = $db->prepareExecuteAll("SELECT eo_project.projectID,eo_project.projectName,eo_project.projectType,eo_project.projectUpdateTime,eo_project.projectVersion,eo_conn_project.userType FROM eo_project INNER JOIN eo_conn_project ON eo_project.projectID = eo_conn_project.projectID WHERE eo_conn_project.userID=? AND eo_project.projectType=? ORDER BY eo_project.projectUpdateTime DESC;", array(
                $userID,
                $projectType
            ));
        }

        if (empty($result))
            return FALSE;
        else
            return $result;
    }

    /**
     * 更改项目
     *
     * @param $projectID int
     *            项目ID
     * @param $projectName string
     *            项目名
     * @param $projectType int
     *            项目类型 [0/1/2/3]=>[Web/App/PC/其他]
     * @param $projectVersion string
     *            项目版本，默认为1.0
     * @return bool
     */
    public function editProject(&$projectID, &$projectName, &$projectType, &$projectVersion)
    {
        $db = getDatabase();

        $db->prepareExecute('UPDATE eo_project SET eo_project.projectType = ?,eo_project.projectName = ?, eo_project.projectUpdateTime = ?, eo_project.projectVersion = ? WHERE eo_project.projectID= ?;', array(
            $projectType,
            $projectName,
            date('Y-m-d H:i:s', time()),
            $projectVersion,
            $projectID
        ));

        if ($db->getAffectRow() > 0)
            return TRUE;
        else
            return FALSE;
    }

    /**
     * 获取项目信息
     *
     * @param $projectID int
     *            项目ID
     * @param $userID int
     *            用户ID
     * @return bool|array
     */
    public function getProject(&$projectID, &$userID)
    {
        $db = getDatabase();
        $project_info = array();
        $project_info = $db->prepareExecute("SELECT eo_project.projectID, eo_project.projectName, eo_project.projectType, eo_project.projectUpdateTime,eo_project.projectVersion,eo_conn_project.userType FROM eo_project INNER JOIN eo_conn_project ON eo_project.projectID = eo_conn_project.projectID WHERE eo_project.projectID= ? AND eo_conn_project.userID = ?;", array(
            $projectID,
            $userID
        ));
        // 获取接口数
        $api_count = $db->prepareExecute('SELECT COUNT(eo_api.apiID) AS count FROM eo_api WHERE eo_api.projectID = ? AND eo_api.removed = 0;', array(
            $projectID
        ));
        $project_info['apiCount'] = $api_count['count'] ? $api_count['count'] : 0;
        // 获取状态码数
        $status_code_count = $db->prepareExecute('SELECT COUNT(eo_project_status_code.codeID) AS count FROM eo_project_status_code LEFT JOIN eo_project_status_code_group ON eo_project_status_code.groupID = eo_project_status_code_group.groupID WHERE eo_project_status_code_group.projectID = ?;', array(
            $projectID
        ));
        $project_info['statusCodeCount'] = $status_code_count['count'] ? $status_code_count['count'] : 0;
        // 获取协作人员数量
        $partner_count = $db->prepareExecute('SELECT COUNT(eo_conn_project.connID) AS count FROM eo_conn_project WHERE eo_conn_project.projectID = ?;', array(
            $projectID
        ));
        $project_info['partnerCount'] = $partner_count['count'] ? $partner_count['count'] : 0;

        $project_info['importURL'] = (is_https() ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . $_SERVER['SCRIPT_NAME'] . '?g=Web&c=AutoGenerate&o=importApi';

        if (empty($project_info))
            return FALSE;
        else
            return $project_info;
    }

    /**
     * 更新项目更新时间
     *
     * @param $projectID int
     *            项目ID
     * @return bool
     */
    public function updateProjectUpdateTime(&$projectID)
    {
        $db = getDatabase();
        $db->prepareExecute('UPDATE eo_project SET eo_project.projectUpdateTime = ? WHERE eo_project.projectID = ?;', array(
            date('Y-m-d H:i:s', time()),
            $projectID
        ));

        if ($db->getAffectRow() > 0)
            return TRUE;
        else
            return FALSE;
    }

//    /**
//     * 获取环境列表
//     *
//     * @param $projectID int
//     *            项目ID
//     * @return bool|array
//     */
//    public function getEnvList(&$projectID)
//    {
//        $db = getDatabase();
//
//        $result = $db->prepareExecuteAll("SELECT eo_project_environment.envID,eo_project_environment.envName,eo_project_environment.envURI FROM eo_project_environment WHERE eo_project_environment.projectID = ?;", array(
//            $projectID
//        ));
//
//        if (empty($result))
//            return FALSE;
//        else
//            return $result;
//    }
//
//    /**
//     * 添加环境
//     *
//     * @param $projectID int
//     *            项目ID
//     * @param $envName string
//     *            环境名
//     * @param $envURI string
//     *            环境地址
//     * @return bool|int
//     */
//    public function addEnv(&$projectID, &$envName, &$envURI)
//    {
//        $db = getDatabase();
//        $result = $db->prepareExecute("INSERT INTO eo_project_environment (eo_project_environment.envName,eo_project_environment.envURI,eo_project_environment.projectID) VALUES (?,?,?);", array(
//            $envName,
//            $envURI,
//            $projectID
//        ));
//
//        if ($db->getAffectRow() > 0)
//            return $db->getLastInsertID();
//        else
//            return FALSE;
//    }
//
//    /**
//     * 删除环境
//     *
//     * @param $projectID int
//     *            项目ID
//     * @param $envID int
//     *            环境ID
//     * @return bool
//     */
//    public function deleteEnv(&$projectID, &$envID)
//    {
//        $db = getDatabase();
//        $result = $db->prepareExecute("DELETE FROM eo_project_environment WHERE eo_project_environment.envID = ? AND eo_project_environment.projectID = ?;", array(
//            $envID,
//            $projectID
//        ));
//
//        if ($db->getAffectRow() > 0)
//            return TRUE;
//        else
//            return FALSE;
//    }
//
//    /**
//     * 修改环境
//     *
//     * @param $envID int
//     *            环境ID
//     * @param $envName string
//     *            环境名
//     * @param $envURI string
//     *            环境地址
//     * @return bool
//     */
//    public function editEnv(&$envID, &$envName, &$envURI)
//    {
//        $db = getDatabase();
//        $result = $db->prepareExecute("UPDATE eo_project_environment SET eo_project_environment.envName = ?,eo_project_environment.envURI = ? WHERE eo_project_environment.envID = ?;", array(
//            $envName,
//            $envURI,
//            $envID
//        ));
//
//        if ($db->getAffectRow() > 0)
//            return TRUE;
//        else
//            return FALSE;
//    }

    /**
     * 获取项目名称
     *
     * @param $projectID int
     *            项目ID
     * @return bool|array
     */
    public function getProjectName(&$projectID)
    {
        $db = getDatabase();
        $result = $db->prepareExecute("SELECT eo_project.projectName FROM eo_project WHERE eo_project.projectID= ?;", array(
            $projectID
        ));

        if (empty($result))
            return FALSE;
        else
            return $result;
    }

    /**
     * 导出项目
     *
     * @param $projectID int
     *            项目ID
     * @return bool|array
     */
    public function dumpProject(&$projectID)
    {
        $db = getDatabase();

        $dumpJson = array();

        // 获取项目信息
        $dumpJson['projectInfo'] = $db->prepareExecute("SELECT * FROM eo_project WHERE eo_project.projectID = ?;", array(
            $projectID
        ));

        // 获取接口父分组信息
        $apiGroupList = $db->prepareExecuteAll("SELECT * FROM eo_api_group WHERE eo_api_group.projectID = ? AND eo_api_group.isChild = 0;", array(
            $projectID
        ));

        $i = 0;
        foreach ($apiGroupList as $apiGroup) {
            $dumpJson['apiGroupList'][$i] = $apiGroup;
            // 获取接口信息
            $apiList = $db->prepareExecuteAll("SELECT eo_api_cache.apiJson FROM eo_api_cache WHERE eo_api_cache.projectID = ? AND eo_api_cache.groupID = ?;", array(
                $projectID,
                $apiGroup['groupID']
            ));
            $dumpJson['apiGroupList'][$i]['apiList'] = array();
            $j = 0;
            foreach ($apiList as $api) {
                $dumpJson['apiGroupList'][$i]['apiList'][$j] = json_decode($api['apiJson'], TRUE);
                // $dumpJson['apiGroupList'][$i]['apiList'][$j]['baseInfo']['starred'] = $api['starred'];
                ++$j;
            }
            $apiGroupChildList = $db->prepareExecuteAll('SELECT * FROM eo_api_group WHERE eo_api_group.projectID = ? AND eo_api_group.parentGroupID = ?', array(
                $projectID,
                $apiGroup['groupID']
            ));
            $k = 0;
            if ($apiGroupChildList) {
                foreach ($apiGroupChildList as $apiChildGroup) {
                    $dumpJson['apiGroupList'][$i]['apiGroupChildList'][$k] = $apiChildGroup;
                    // 获取接口信息
                    $apiList = $db->prepareExecuteAll("SELECT * FROM eo_api_cache WHERE eo_api_cache.projectID = ? AND eo_api_cache.groupID = ?;", array(
                        $projectID,
                        $apiChildGroup['groupID']
                    ));
                    $dumpJson['apiGroupList'][$i]['apiGroupChildList'][$k]['apiList'] = array();
                    $l = 0;
                    foreach ($apiList as $api) {
                        $dumpJson['apiGroupList'][$i]['apiGroupChildList'][$k]['apiList'][$l] = json_decode($api['apiJson'], TRUE);
                        // $dumpJson['apiGroupList'][$i]['apiGroupChildList'][$k]['apiList'][$l]['baseInfo']['starred'] = $api['starred'];
                        ++$l;
                    }
                    ++$k;
                }
            }
            ++$i;
        }

        // 获取状态码分组信息
        $statusCodeGroupList = $db->prepareExecuteAll("SELECT * FROM eo_project_status_code_group WHERE eo_project_status_code_group.projectID = ? AND isChild = 0;", array(
            $projectID
        ));

        $i = 0;
        foreach ($statusCodeGroupList as $statusCodeGroup) {
            $dumpJson['statusCodeGroupList'][$i] = $statusCodeGroup;

            // 获取状态码信息
            $statusCodeList = $db->prepareExecuteAll("SELECT * FROM eo_project_status_code WHERE eo_project_status_code.groupID = ?;", array(
                $statusCodeGroup['groupID']
            ));

            $j = 0;
            foreach ($statusCodeList as $statusCode) {
                $dumpJson['statusCodeGroupList'][$i]['statusCodeList'][$j] = $statusCode;
                ++$j;
            }
            $statusCodeGroupChildList = $db->prepareExecuteAll("SELECT * FROM eo_project_status_code_group WHERE eo_project_status_code_group.projectID = ? AND parentGroupID = ?;", array(
                $projectID,
                $statusCodeGroup['groupID']
            ));
            $k = 0;
            if ($statusCodeGroupChildList) {
                foreach ($statusCodeGroupChildList as $statusCodeChildGroup) {
                    $dumpJson['statusCodeGroupList'][$i]['statusCodeGroupChildList'][$k] = $statusCodeChildGroup;

                    // 获取状态码信息
                    $statusCodeList = $db->prepareExecuteAll("SELECT * FROM eo_project_status_code WHERE eo_project_status_code.groupID = ?;", array(
                        $statusCodeGroup['groupID']
                    ));

                    $l = 0;
                    foreach ($statusCodeList as $statusCode) {
                        $dumpJson['statusCodeGroupList'][$i]['statusCodeGroupChildList'][$k]['statusCodeList'][$l] = $statusCode;
                        ++$l;
                    }
                    ++$k;
                }
            }
            ++$i;
        }

        if (empty($dumpJson))
            return FALSE;
        else
            return $dumpJson;
    }

    /**
     * 获取api数量
     *
     * @param $projectID int
     *            项目ID
     * @return bool|array
     */
    public function getApiNum(&$projectID)
    {
        $db = getDatabase();

        $result = $db->prepareExecute('SELECT COUNT(*) AS num FROM eo_api WHERE eo_api.removed = 0 AND eo_api.groupID IN (SELECT groupID FROM eo_api_group WHERE eo_api.projectID = ?);', array(
            $projectID
        ));

        if (isset($result))
            return $result;
        else
            return FALSE;
    }
}

?>