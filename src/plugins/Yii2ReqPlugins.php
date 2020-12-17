<?php
/******************************************************************************
 * Copyright 2020 NAVER Corp.                                                 *
 *                                                                            *
 * Licensed under the Apache License, Version 2.0 (the "License");            *
 * you may not use this file except in compliance with the License.           *
 * You may obtain a copy of the License at                                    *
 *                                                                            *
 *     http://www.apache.org/licenses/LICENSE-2.0                             *
 *                                                                            *
 * Unless required by applicable law or agreed to in writing, software        *
 * distributed under the License is distributed on an "AS IS" BASIS,          *
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.   *
 * See the License for the specific language governing permissions and        *
 * limitations under the License.                                             *
 ******************************************************************************/

namespace Plugins;
require_once "__init__.php";

class Yii2ReqPlugins
{
    public static $_intance = null;
    public $tid = null;
    public $sid = null;
    public $psid = null;
    public $pname = null;
    public $ptype = null;
    public $ah = null;
    public $app_name = null;
    public $app_id = null;
    private $curNextSpanId = '';
    private $isLimit = false;

    public function traceLimit()
    {
        return $this->isLimit;
    }

    public static function instance()
    {
        if (self::$_intance == null)
        {
            self::$_intance = new Yii2ReqPlugins();
        }
        return self::$_intance;
    }

    private function initTrace()
    {
        while (pinpoint_end_trace() > 0);
    }

    private function __construct()
    {
        $this->initTrace();

        pinpoint_start_trace();
        pinpoint_add_clue(PP_SERVER_TYPE, PP_PHP);
        pinpoint_add_clue(PP_INTERCEPTOR_NAME, "PHP Request: ". php_sapi_name());

        if(isset($_SERVER['REQUEST_URI']))
        {
            pinpoint_add_clue(PP_REQ_URI, $_SERVER['REQUEST_URI']);
        }
        elseif(isset($_SERVER['argv']))
        {
            pinpoint_add_clue(PP_REQ_URI, implode(" ", $_SERVER['argv']));
        }

        if(isset($_SERVER['REMOTE_ADDR']))
        {
            pinpoint_add_clue(PP_REQ_CLIENT, $_SERVER["REMOTE_ADDR"]);
        }

        if(isset($_SERVER['HTTP_HOST']))
        {
            pinpoint_add_clue(PP_REQ_SERVER, $_SERVER["HTTP_HOST"]);
        }

        $this->app_name = defined('APPLICATION_NAME') ? APPLICATION_NAME : pinpoint_app_name();
        pinpoint_add_clue(PP_APP_NAME, $this->app_name);

        $this->app_id = defined('APPLICATION_ID') ? APPLICATION_ID : pinpoint_app_id();
        pinpoint_add_clue(PP_APP_ID, $this->app_id);

        if(isset($_SERVER[PP_HEADER_PSPANID]) || array_key_exists(PP_HEADER_PSPANID, $_SERVER))
        {
            $this->psid = $_SERVER[PP_HEADER_PSPANID];
            pinpoint_add_clue(PP_PARENT_SPAN_ID, $this->psid);
        }

        if(isset($_SERVER[PP_HEADER_SPANID]) || array_key_exists(PP_HEADER_SPANID, $_SERVER))
        {
            $this->sid = $_SERVER[PP_HEADER_SPANID];
        }
        else
        {
            $this->sid = $this->generateSpanID();
        }

        if(isset($_SERVER[PP_HEADER_TRACEID]) || array_key_exists(PP_HEADER_TRACEID, $_SERVER))
        {
            $this->tid = $_SERVER[PP_HEADER_TRACEID];
        }
        else
        {
            $this->tid = $this->generateTransactionID();
        }

        if(isset($_SERVER[PP_HEADER_PAPPNAME]) || array_key_exists(PP_HEADER_PAPPNAME, $_SERVER))
        {
            $this->pname = $_SERVER[PP_HEADER_PAPPNAME];
            pinpoint_add_clue(PP_PARENT_NAME, $this->pname);
        }

        if(isset($_SERVER[PP_HEADER_PAPPTYPE]) || array_key_exists(PP_HEADER_PAPPTYPE, $_SERVER))
        {
            $this->ptype = $_SERVER[PP_HEADER_PAPPTYPE];
            pinpoint_add_clue(PP_PARENT_TYPE, $this->ptype);
        }

        if(isset($_SERVER[PP_HEADER_PINPOINT_HOST]) || array_key_exists(PP_HEADER_PINPOINT_HOST, $_SERVER))
        {
            $this->ah = $_SERVER[PP_HEADER_PINPOINT_HOST];
            pinpoint_add_clue(PP_PARENT_HOST, $this->ah);
        }

        if(isset($_SERVER[PP_HEADER_NGINX_PROXY]) || array_key_exists(PP_HEADER_NGINX_PROXY, $_SERVER))
        {
            pinpoint_add_clue(PP_NGINX_PROXY, $_SERVER[PP_HEADER_NGINX_PROXY]);
        }

        if(isset($_SERVER[PP_HEADER_APACHE_PROXY]) || array_key_exists(PP_HEADER_APACHE_PROXY, $_SERVER))
        {
            pinpoint_add_clue(PP_APACHE_PROXY, $_SERVER[PP_HEADER_APACHE_PROXY]);
        }

        if(isset($_SERVER[PP_HEADER_SAMPLED]) || array_key_exists(PP_HEADER_SAMPLED, $_SERVER))
        {
            if ($_SERVER[PP_HEADER_SAMPLED] == PP_NOT_SAMPLED)
            {
                $this->isLimit = true;
                //drop this request. collector could not receive any thing
                pinpoint_drop_trace();
            }
        }
        else
        {
            $this->isLimit = pinpoint_tracelimit();
        }

        pinpoint_add_clue(PP_TRANSCATION_ID, $this->tid);
        pinpoint_add_clue(PP_SPAN_ID, $this->sid);

    }
    public function __destruct()
    {
        // reset limit
        $this->isLimit = false;
        if (($http_response_code = http_response_code()) != null)
        {
            pinpoint_add_clues(PP_HTTP_STATUS_CODE, $http_response_code);
        }
        pinpoint_end_trace();
    }

    public function generateSpanID()
    {
        try
        {
            $this->curNextSpanId = mt_rand();//random_int(-99999999,99999999);
            return $this->curNextSpanId;
        }
        catch (\Exception $e)
        {
            return rand();
        }
    }

    public function getCurNextSpanId()
    {
        return $this->curNextSpanId;
    }

    public function generateTransactionID()
    {
        return  $this->app_id . '^' . strval(pinpoint_start_time()) . '^' . strval(pinpoint_unique_id());
    }
}
