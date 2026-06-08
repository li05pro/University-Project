<?php
/**
 * 源泉动态网站 - 数据验证类
 * 提供全面的数据验证功能
 */

class Validator {
    private $data = [];
    private $errors = [];
    private $rules = [];
    
    /**
     * 构造函数
     * @param array $data 要验证的数据
     */
    public function __construct(array $data = []) {
        $this->data = $data;
    }
    
    /**
     * 设置验证规则
     * @param array $rules 验证规则数组
     * @return $this
     */
    public function rules(array $rules) {
        $this->rules = $rules;
        return $this;
    }
    
    /**
     * 执行验证
     * @return bool
     */
    public function validate() {
        $this->errors = [];
        
        foreach ($this->rules as $field => $ruleString) {
            $rules = explode('|', $ruleString);
            $value = $this->data[$field] ?? null;
            
            foreach ($rules as $rule) {
                $this->checkRule($field, $value, trim($rule));
            }
        }
        
        return empty($this->errors);
    }
    
    /**
     * 检查单个规则
     * @param string $field 字段名
     * @param mixed $value 字段值
     * @param string $rule 规则
     */
    private function checkRule($field, $value, $rule) {
        // 解析规则参数
        $params = [];
        if (strpos($rule, ':') !== false) {
            list($ruleName, $paramStr) = explode(':', $rule, 2);
            $params = explode(',', $paramStr);
        } else {
            $ruleName = $rule;
        }
        
        switch ($ruleName) {
            case 'required':
                if (empty($value) && $value !== '0') {
                    $this->addError($field, '该字段不能为空');
                }
                break;
                
            case 'min':
                $min = intval($params[0] ?? 0);
                if (is_string($value) && mb_strlen($value) < $min) {
                    $this->addError($field, "长度不能少于{$min}个字符");
                } elseif (is_numeric($value) && $value < $min) {
                    $this->addError($field, "不能小于{$min}");
                }
                break;
                
            case 'max':
                $max = intval($params[0] ?? 0);
                if (is_string($value) && mb_strlen($value) > $max) {
                    $this->addError($field, "长度不能超过{$max}个字符");
                } elseif (is_numeric($value) && $value > $max) {
                    $this->addError($field, "不能大于{$max}");
                }
                break;
                
            case 'between':
                $min = intval($params[0] ?? 0);
                $max = intval($params[1] ?? 0);
                $len = is_string($value) ? mb_strlen($value) : $value;
                if ($len < $min || $len > $max) {
                    $this->addError($field, "长度必须在{$min}-{$max}个字符之间");
                }
                break;
                
            case 'email':
                if (!empty($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $this->addError($field, '邮箱格式不正确');
                }
                break;
                
            case 'url':
                if (!empty($value) && !filter_var($value, FILTER_VALIDATE_URL)) {
                    $this->addError($field, 'URL格式不正确');
                }
                break;
                
            case 'numeric':
                if (!empty($value) && !is_numeric($value)) {
                    $this->addError($field, '必须是数字');
                }
                break;
                
            case 'integer':
                if (!empty($value) && !filter_var($value, FILTER_VALIDATE_INT)) {
                    $this->addError($field, '必须是整数');
                }
                break;
                
            case 'alpha':
                if (!empty($value) && !ctype_alpha($value)) {
                    $this->addError($field, '只能包含字母');
                }
                break;
                
            case 'alpha_num':
                if (!empty($value) && !ctype_alnum($value)) {
                    $this->addError($field, '只能包含字母和数字');
                }
                break;
                
            case 'alpha_dash':
                if (!empty($value) && !preg_match('/^[a-zA-Z0-9_-]+$/', $value)) {
                    $this->addError($field, '只能包含字母、数字、下划线和横线');
                }
                break;
                
            case 'mobile':
                if (!empty($value) && !preg_match('/^1[3-9]\d{9}$/', $value)) {
                    $this->addError($field, '手机号格式不正确');
                }
                break;
                
            case 'id_card':
                if (!empty($value) && !$this->validateIdCard($value)) {
                    $this->addError($field, '身份证号格式不正确');
                }
                break;
                
            case 'in':
                if (!empty($value) && !in_array($value, $params)) {
                    $this->addError($field, '值不在允许范围内');
                }
                break;
                
            case 'not_in':
                if (!empty($value) && in_array($value, $params)) {
                    $this->addError($field, '值在禁止范围内');
                }
                break;
                
            case 'regex':
                if (!empty($value) && !preg_match($params[0] ?? '//', $value)) {
                    $this->addError($field, '格式不正确');
                }
                break;
                
            case 'confirm':
                $confirmField = $params[0] ?? $field . '_confirm';
                if ($value !== ($this->data[$confirmField] ?? null)) {
                    $this->addError($field, '两次输入不一致');
                }
                break;
                
            case 'different':
                if ($value === ($this->data[$params[0] ?? ''] ?? null)) {
                    $this->addError($field, '不能与指定字段相同');
                }
                break;
                
            case 'date':
                if (!empty($value) && !strtotime($value)) {
                    $this->addError($field, '日期格式不正确');
                }
                break;
                
            case 'date_format':
                $format = $params[0] ?? 'Y-m-d';
                if (!empty($value)) {
                    $d = DateTime::createFromFormat($format, $value);
                    if (!$d || $d->format($format) !== $value) {
                        $this->addError($field, "日期格式必须是 {$format}");
                    }
                }
                break;
                
            case 'before':
                if (!empty($value)) {
                    $beforeDate = $params[0] ?? 'now';
                    $beforeTime = strtotime($beforeDate) ?: strtotime('now');
                    if (strtotime($value) >= $beforeTime) {
                        $this->addError($field, '日期必须在指定日期之前');
                    }
                }
                break;
                
            case 'after':
                if (!empty($value)) {
                    $afterDate = $params[0] ?? 'now';
                    $afterTime = strtotime($afterDate) ?: strtotime('now');
                    if (strtotime($value) <= $afterTime) {
                        $this->addError($field, '日期必须在指定日期之后');
                    }
                }
                break;
                
            case 'json':
                if (!empty($value)) {
                    json_decode($value);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        $this->addError($field, '必须是有效的JSON格式');
                    }
                }
                break;
                
            case 'array':
                if (!empty($value) && !is_array($value)) {
                    $this->addError($field, '必须是数组');
                }
                break;
                
            case 'unique':
                // 需要在数据库中检查唯一性
                // 参数格式: table,column,exceptId
                if (!empty($value) && !empty($params[0])) {
                    $table = $params[0];
                    $column = $params[1] ?? $field;
                    $exceptId = $params[2] ?? null;
                    
                    try {
                        $db = getDB();
                        $sql = "SELECT COUNT(*) FROM {$table} WHERE {$column} = ?";
                        $params_arr = [$value];
                        
                        if ($exceptId) {
                            $sql .= " AND id != ?";
                            $params_arr[] = $exceptId;
                        }
                        
                        $stmt = $db->prepare($sql);
                        $stmt->execute($params_arr);
                        
                        if ($stmt->fetchColumn() > 0) {
                            $this->addError($field, '该值已存在');
                        }
                    } catch (PDOException $e) {
                        // 数据库错误，不添加验证错误
                    }
                }
                break;
                
            case 'exists':
                // 检查数据库中是否存在
                if (!empty($value) && !empty($params[0])) {
                    $table = $params[0];
                    $column = $params[1] ?? $field;
                    
                    try {
                        $db = getDB();
                        $stmt = $db->prepare("SELECT COUNT(*) FROM {$table} WHERE {$column} = ?");
                        $stmt->execute([$value]);
                        
                        if ($stmt->fetchColumn() == 0) {
                            $this->addError($field, '该值不存在');
                        }
                    } catch (PDOException $e) {
                        // 数据库错误，不添加验证错误
                    }
                }
                break;
        }
    }
    
    /**
     * 添加错误信息
     * @param string $field 字段名
     * @param string $message 错误信息
     */
    private function addError($field, $message) {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = [];
        }
        $this->errors[$field][] = $message;
    }
    
    /**
     * 获取所有错误
     * @return array
     */
    public function errors() {
        return $this->errors;
    }
    
    /**
     * 获取第一个错误
     * @return string|null
     */
    public function firstError() {
        if (empty($this->errors)) {
            return null;
        }
        $first = reset($this->errors);
        return is_array($first) ? $first[0] : $first;
    }
    
    /**
     * 获取指定字段的错误
     * @param string $field 字段名
     * @return array
     */
    public function getError($field) {
        return $this->errors[$field] ?? [];
    }
    
    /**
     * 是否有错误
     * @return bool
     */
    public function hasErrors() {
        return !empty($this->errors);
    }
    
    /**
     * 验证身份证号
     * @param string $idCard 身份证号
     * @return bool
     */
    private function validateIdCard($idCard) {
        $idCard = strtoupper($idCard);
        
        // 基本格式验证
        if (!preg_match('/^\d{17}[\dX]$/', $idCard)) {
            return false;
        }
        
        // 加权因子
        $weights = [7, 9, 10, 5, 8, 4, 2, 1, 6, 3, 7, 9, 10, 5, 8, 4, 2];
        // 校验码
        $checkCodes = ['1', '0', 'X', '9', '8', '7', '6', '5', '4', '3', '2'];
        
        $sum = 0;
        for ($i = 0; $i < 17; $i++) {
            $sum += intval($idCard[$i]) * $weights[$i];
        }
        
        return $checkCodes[$sum % 11] === $idCard[17];
    }
}

/**
 * 快捷验证函数
 * @param array $data 数据
 * @param array $rules 规则
 * @return array ['valid' => bool, 'errors' => array, 'first' => string]
 */
function validate(array $data, array $rules) {
    $validator = new Validator($data);
    $validator->rules($rules);
    
    $valid = $validator->validate();
    
    return [
        'valid' => $valid,
        'errors' => $validator->errors(),
        'first' => $validator->firstError()
    ];
}

/**
 * 文章数据验证
 * @param array $data 文章数据
 * @return array
 */
function validateArticle(array $data) {
    return validate($data, [
        'title' => 'required|min:2|max:200',
        'content' => 'required|min:10|max:50000',
        'category_id' => 'required|integer|min:1',
        'tags' => 'max:255',
        'summary' => 'max:500'
    ]);
}

/**
 * 用户注册数据验证
 * @param array $data 用户数据
 * @return array
 */
function validateUserRegister(array $data) {
    return validate($data, [
        'username' => 'required|alpha_dash|min:3|max:20|unique:user,username',
        'password' => 'required|min:6|max:32|confirm',
        'email' => 'required|email|max:100|unique:user,email',
        'nickname' => 'required|min:2|max:50',
        'mobile' => 'mobile'
    ]);
}

/**
 * 用户登录数据验证
 * @param array $data 用户数据
 * @return array
 */
function validateUserLogin(array $data) {
    return validate($data, [
        'username' => 'required|min:3|max:50',
        'password' => 'required|min:6|max:32'
    ]);
}

/**
 * 评论数据验证
 * @param array $data 评论数据
 * @return array
 */
function validateComment(array $data) {
    return validate($data, [
        'article_id' => 'required|integer|min:1',
        'content' => 'required|min:2|max:1000',
        'parent_id' => 'integer|min:0'
    ]);
}

/**
 * 搜索参数验证
 * @param array $data 搜索参数
 * @return array
 */
function validateSearch(array $data) {
    return validate($data, [
        'keyword' => 'max:100',
        'page' => 'integer|min:1',
        'pageSize' => 'integer|between:1,50',
        'category_id' => 'integer|min:0'
    ]);
}
