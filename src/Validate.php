<?php

class Ep_Validate
{
    /**
     * 校验错误
     *
     * @var array
     */
    protected $errors = array();

    /**
     * 规则错误信息
     *
     * @var array
     */
    protected $rulesErrorMsg = array();
    
    /**
     * 所有支持的校验规则
     *
     * @var array
     */
    protected $rules = array();

    /**
     * sub schema
     *
     * @var array
     */
    protected $defined = array();

    /**
     * 内置校验
     *
     * @var array
     */
    protected $builtin = array(
        'json',
        'string',
        'max',
        'min',
        'int',
        'enum',
        'json_array',
        'json_object',
    );

    /**
     * 用户输入的数据
     *
     * [
     *      'name' => '三傻',
     *      'age' => 200
     * ]
     *
     * @var array
     */
    protected $input = array();

    /**
     * 用户输入的校验规则
     *
     * [
     *      'key' => 'require|json'
     * ]
     *
     * @var array
     */
    protected $config = array();

    /**
     *
     * Ep_Validate constructor.
     *
     * @param array $input
     * @param array $config
     * @param array $refDefined
     */
    public function __construct(array $input, array $config = [], array $refDefined = array())
    {
        $this->input = $input;
        $this->config = $config;

        foreach ($this->builtin as $rule)
        {
            $this->rules[$rule] = call_user_func(
                array($this, $this->getRuleName($rule))
            );
        }

        $this->setRefDefined($refDefined);
    }

    /**
     * 调用诸多 check 逻辑进行 check.
     *
     * @return bool
     */
    public function ok()
    {
        if (!$this->input)
        {
            $this->errors[] = 'invalid arguments';
            return false;
        }

        $rules = $this->prepare($this->config);

        foreach ((array)$rules as $key => $validates)
        {
            foreach ($validates as $check => $params)
            {
                if ($check == 'required')
                {
                    if (! isset($this->input[$key]))
                    {
                        $this->errors[] = "$key is required.";
                        return false;
                    }
                } else
                {
                    if (isset($this->input[$key]) && ! $this->check($this->input[$key], $check, $params))
                    {
                        $this->errors[] = "$key = " . $this->input[$key] . " invalid.";
                        return false;
                    }
                }
            }
        }

        return true;
    }

    /**
     * 获取 Validate 错误信息
     *
     * @return string
     */
    public function getValidateError()
    {
        return implode("\n ", $this->errors);
    }

    /**
     * 字符最大不得超过
     *
     * ['key' => 'string|max: 10']
     *
     * @return Closure
     */
    public function ruleMax()
    {
        return function ($value, $max)
        {
            return strlen($value) <= $max;
        };
    }

    /**
     * 字符最小限制
     *
     * ['name' => 'string|min: 10']
     *
     * @return Closure
     */
    public function ruleMin()
    {
        return function ($value, $min)
        {
            return strlen($value) >= $min;
        };
    }

    /**
     * 提供的必须是一个 json 串
     *
     * [ 'data' => 'json']
     *
     * @return Closure
     */
    public function ruleJson()
    {
        return function ($value)
        {
            $this->registerRuleErrorMsg('json', $value . ' can\'t json_decode to Array or Object and maybe json_decode result not is a array.');

            $json = json_decode($value, true);

            if ($json === null || !is_array($json))
            {
                return false;
            }

            return true;
        };
    }

    /**
     * 校验提供的 json 解析后是个数字数组
     *
     * @return Closure
     */
    public function ruleJsonArray()
    {
        return function ($value)
        {
            $this->registerRuleErrorMsg('json_array', $value . ' can\'t json_decode to Array or maybe it a assoc array.');

            $json = json_decode($value, true);

            if ($json === null || !is_array($json))
            {
                return false;
            }

            $keyCombine = join('', array_keys($json));

            if (!is_numeric($keyCombine))
            {
                return false;
            }

            return true;
        };
    }

    /**
     * 校验给定 json 解析后是个 assoc 数组
     *
     * @return Closure
     */
    public function ruleJsonObject()
    {
        return function ($value)
        {
            $json = json_decode($value, true);

            if ($json === null || !is_array($json))
            {
                return false;
            }

            $keyCombine = join('', array_keys($json));

            if (is_numeric($keyCombine))
            {
                return false;
            }

            return true;
        };
    }

    /**
     * 提供必须是字符串
     *
     * @return Closure
     */
    public function ruleString()
    {
        return function ($value)
        {
            return is_string($value);
        };
    }

    /**
     * 提供必须是数字
     *
     * @return Closure
     */
    public function ruleInt()
    {
        return function ($value)
        {
            return is_numeric($value);
        };
    }

    /**
     * 提供常量的检查
     *
     * @return Closure
     */
    public function ruleEnum()
    {
        return function ()
        {
            if (func_num_args() <= 1)
            {
                $this->registerRuleErrorMsg('enum', 'can\'t given enum value.');

                return false;
            }

            $arguments = func_get_args();
            $value = array_shift($arguments);

            return in_array($value, $arguments);
        };
    }

    /**
     * check or get rule name
     *
     * @param $ruleString
     * @return string
     */
    protected function getRuleName($ruleString)
    {
        if (!is_string($ruleString) || !preg_match('@[a-zA-z0-9_]+@', $ruleString))
        {
            throw new Exception(
                'Invalid arguments'
            );
        }

        $words = explode('_', $ruleString);

        $words = array_map(function ($w)
        {
            return ucfirst($w);
        }, $words);

        return 'rule' . implode('', $words);
    }


    /**
     * 对用户的配置进行预处理
     *
     * @param array $config
     * @return bool
     */
    protected function prepare(array $config)
    {
        if (!$config)
        {
            return [];
        }

        $result = [];

        foreach ($config as $key => $option)
        {
            if ($key === '$define')
            {
                $this->setRefDefined((array) $option);
                continue;
            }

            $rules = array();
            foreach (explode('|', $option) as $one)
            {
                if (($p = strpos($one, ':')) !== false)
                {
                    $rules[substr($one, 0, $p)] = explode(',', substr($one, $p + 1));
                } else
                {
                    $rules[$one] = array();
                }
            }

            $result[$key] = $rules;
        }

        return $result;
    }

    /**
     * 调用预设 check 逻辑校验
     *
     * @param array $value 输入字段的值
     * @param array $rule 校验规则
     * @param array $params 校验函数的参数
     * @return bool|mixed
     */
    protected function check($value, $rule, $params = array())
    {
        if (!is_array($value))
        {
            $value = [$value];
        }

        foreach ($value as $val)
        {
            // 嵌套校验
            if ($rule === '$ref')
            {
                if (count($params) != 1 || !is_string($params[0]))
                {
                    $this->errors[] = '$ref value must a string.';
                    return false;
                }

                if (! ($_rules = $this->getRefDefined($params[0]))) {
                    $this->errors[] = '$ref:' . $params[0] . ' not defined.';
                    return false;
                }

                if (is_string($val))
                {
                    $val = json_decode($val, true);
                }

                $validate = new self($val, $_rules, $this->getRefDefined());

                if (!$validate->ok())
                {
                    $this->errors[] = $validate->getValidateError();
                    return false;
                }
            }

            // 无规则直接通过
            if (! isset($this->rules[$rule]))
            {
                return true;
            }

            $check = $this->rules[$rule];

            array_unshift($params, $val); // 准备 check 函数的参数

            if (! call_user_func_array($check, $params))
            {
                $this->errors[] = 'check validate rule `'.$rule.'` failed.'
                    . isset($this->rulesErrorMsg[$rule]) ? $this->rulesErrorMsg[$rule] : '';

                return false;
            }
        }

        return true;
    }

    /**
     * 注册规则错误信息，提供更优质的错误提醒
     *
     * @param string $ruleName
     * @param string $errorMsg
     * @return object
     */
    protected function registerRuleErrorMsg($ruleName, $errorMsg)
    {
        $this->rulesErrorMsg[$ruleName] = $errorMsg;

        return $this;
    }

    /**
     * set  $ref defined.
     *
     * @param array $refDefined
     * @return $this
     */
    protected function setRefDefined(array $refDefined)
    {
        $this->defined = array_merge($this->defined, $refDefined);

        return $this;
    }

    /**
     * get $ref defined.
     *
     * @param string $ref
     * @return array
     */
    protected function getRefDefined($ref = null)
    {
        if ($ref)
        {
            return $this->defined[$ref];
        }
        return $this->defined;
    }
}