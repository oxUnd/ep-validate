# ep-validate
 Another validate tool


## 说明

校验参数可能是遇到最多的问题，特别是在导入数据的时候。一个定义清晰的校验工具就不可获取了。

宏观的去考察一下，有个 json schema 的项目特别令人愉悦，但奈何类库代码颇多，有时候校验并不需要引入如此庞大的一套体系。

在纠结了几天后，决定还是简单来一个吧，自己用着比较舒服，遂放到这块。


## 安装

```
composer install xiangshouding/ep-validate
```

## 使用

```php

$data = array(
    'name' => 'bozlll',
    'age' => 27,
    'gender' => 'm',
    'ext' => array(
        'school' => 'HIT',
        'class' => 4
    )
);

$rules = array(
    'name' => 'required|string|max:30',
    'age' => 'required|int',
    'gender' => 'required|enum:m,f',
    'ext' => '$ref:ext',
    '$define' => array(
        'ext' => array(
            'school' => 'string',
            'class' => 'string'
        )
    ),
);

$validate = new Ep_Validate($data, $rules);

if (!$validate->ok()) {
    throw new Exception($validate->getValidateError());
}

...

```

## 检验规则

...
