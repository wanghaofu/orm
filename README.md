用说明
=======

##
#### 功能改进
  资源配置全部由外部注入

  目前内部链接传入的数据库参数无效






理论上依赖关系应该是`{业务包}=>{king/core}=>{king/orm}`，本说明以此为前提

安装`king/core`依赖后，在项目目录下的`./vendor/king/orm/schema`目录下为所有业务表的yml定义。yml文件名请保持__和表名一致__

```yml
package: [包名，不影响SQL，仅影响数据结构视图]
name: [必填表名]
columns:
  -
    name: [必填字段名]
    type: [必填字段类型 integer|string|其他见doctrine文档]
    comment: [字段说明(field comment)]
    option:
      autoincrement: [true 表示自增]
      length: [string时有效 字段长度（有则为varchar，不声明则为text）]
      customSchemaOptions:
        collation: latin1_bin #latin1字符集（默认UTF8）
      # 其他选项见doctrine文档
    relation: #关联声明，不影响SQL，仅影响数据结构视图
      [关联表]:
        comment: [关联说明]
  -
    name: [第二个字段名]
    type: int
    # ……
pk: # 主键声明
  - account_id

indexes: # 索引声明
  -
    unique: true # 唯一索引
    columns: ＃ 索引字段列表
      - [索引字段一]
      - [索引字段二]
    comment: [索引说明]

comment: |
  ## XXXX表
  
  markdown说明文字

```

参考 

+ doctrine文档 <http://docs.doctrine-project.org/projects/doctrine-dbal/en/latest/reference/schema-representation.html#column>

yml定义完成后，使用`./vendor/bin/korm.php`命令行工具进行操作


### 命令行工具

#### ./vendor/bin/korm.php schema

生成SQL并打印

参数

+ mode 默认create，传drop改为生成drop语句，传migrate改为差量alter语句

选项

+ `--exec` 增加这个选项来直接在开发环境执行（而不输出）
+ `--table="..."` 指定表名（默认所有表）
+ `--db="..."` 指定库名（默认king）

#### ./vendor/bin/korm.php generate

生成类代码（如果在开发机上执行，可能需要下载到本机后提交git）

选项

+ `--db="..."` 指定库名（默认king）
+ `--table="..."` 指定表名（默认所有表）

### 每个表对应四个类?

#### 生成 vs 业务

脚本自动生成的类位于`King\Orm\Generated`，由yml定义文件自动生成

业务类继承自动生成的类，在其中写业务代码，从而达到自动生成代码不影响业务的效果

业务相关的Model/Schema类放置于`King\Core\Model`下

#### Schema类

Schema类代表“如何查询”，其实例代表一次查询

业务类模版如下（PHPStorm格式）

```php
<?php namespace King\Core\Model;

use King\Orm\Generated\Ko${TableName}Schema;

/**
 * User: ${USER}
 * Date: ${DATE}
 *
 * @method static ${TableName}Model findByPk(${DS}id)
 * @method ${TableName}Model findFirst
 * @method ${TableName}Model[]|\Traversable find
 */
class ${TableName}Schema extends Ko${TableName}Schema
{
    const MODEL = ${TableName}Model::class;
}
```


自动生成的成员有：

+ `MODEL`常量，Model类名，往往需要业务类覆盖
+ `TABLE`常量，代表DB中的实际表名
+ `FLD_XXX`常量，代表字段在DB中的实际字段名，用于拼接查询
+ 其他meta信息

基本用法：

```php

//主键查找
$order = OrderSchema::findByPk('201505111236000018');

//查询拼接
$q = OrderSchema::query();
$orders = $q
    //用$q->param 进行参数绑定（PDO bindValue）
    //所有参数均会拼接到sql中（空格分割）
    ->gt(OrderSchema::FLD_CREATED_TIME, 0)
    //也可以在Schema中自定义过滤方法
    ->andUidIs($uid)
    //或者findFirst拿一个
    ->find();
```

常见业务成员：常用的查询条件拼接方法，例如[andUidIs](http://git./php/king-core/blob/315be0746be33d60c5917c014e377bd52329fb09/src/Model/OrderSchema.php#L17-20)

##### 防注入

以下过滤方法自带参数绑定，可以直接传值

+ gt(e)
+ lt(e)
+ (aes)eq
+ (aes)in

而where相关方法，传入的值必须手动参数绑定(即pdo bind)放止SQL注入

+ where
+ andWhere

```php
//暂存query实例
$q = XXXSchema::query();
//通过$q->param($value) 进行参数绑定
$q->andWhere($field, '=', $query->param($userValue));

```

#### Model类

Model类的实例代表具体的一行数据

业务类模版如下：

```php
<?php namespace King\Core\Model;

use King\Orm\Generated\Ko${TableName}Model;

/**
 * User: ${USER}
 * Date: ${DATE}
 */
class ${TableName}Model extends Ko${TableName}Model
{
    const SCHEMA = ${TableName}Schema::class;
}
```


自动生成的成员有：

+ `SCHEMA`常量，Schema类名，往往需要业务类覆盖
+ `$xxx`属性，字段
+ 其他meta信息

基本用法：

```php
//创建并插入
$order = new OrderModel();
$order->status = OrderModel::STATUS_LOCKED;
$order->uid = $uid;
$order->orderId = $orderId;
$order->quantity = 3;
$order->transactionTarget = 33;
$order->transactionType = 1;

$order->save();

//保存修改
$order->uid = 12345;
$order->save();

//删除
$order->remove();
```

常见业务成员：

+ 枚举字段的值常量`XXModel::STATUS_YY`
+ 计算衍生字段 `getXXX`
+ ...

### Model Hook

见`\King\Orm\Base\BaseModel::triggerSaveHook`方法

每次save的时候，都会根据model当前状态触发`beforeInsert`/`beforeUpdate`方法和`beforeSave`方法。
覆盖这些方法可以实现诸如自动更新timestamp等功能

```php
    protected function beforeSave()
    {
        parent::beforeSave();
        $this->maintainTimestamps();
    }
```

（BaseModel提供了maintainTimestamps实现，维护created_time和updated_time）

业务Model可以覆盖这些beforeXX方法实现“新增前”，“更新前”或“保存前”的逻辑

### Field Map

见`\King\Orm\Base\BaseModel::mapField`方法

BaseModel提供了字段映射机制，支持字段在数据库中的格式和在Model中的格式自动双向转换，例如数据库的bigint对应php的string，可以在代码中自动将其转化为`BigNumber`类的实例，并在保存入库前自动转回string

默认提供了`BigNumber`类包装和`json_encode`/`json_decode`包装两种，直接覆盖对应的配置即可

```php
    protected static $_bigNumFields = [];
    protected static $_jsonFields = [];
```

也可以通过覆盖`mapField`实现其他形式的字段格式转换

### 乐观锁

具备version字段的表一般引用`\King\Core\Model\VersionTrait`带入乐观锁功能。

原理：

每次更新时，version字段会更新为之前的值+1，并且在where子句中限定version为原version值。根据update结果的`affected_rows`计数判断更新是否成功

如果某次读取后，另一个进程抢先更新，则where中的version值匹配不上，`affected_rows`为0，此时代码便得知发生了异步冲突

### ListProvider和Join

[IListProvider](http://git./php/king-orm/blob/master/src/ListProvider/IListProvider.php)是分页列表的抽象，具备获取分页信息`getPager`和获取某一页的数据`fetch`两个方法。

通常使用[QueryListProvider](http://git./php/king-orm/blob/master/src/ListProvider/QueryListProvider.php)，传入一个拼接到一半的query(Schema)对象，类的内部负责组成合适的`COUNT(*)`语句和`SELECT ... LIMIT`语句来拉取数据

一般来说ListProvider都会支持[MapperAwareTrait](http://git./php/king-orm/blob/master/src/MapperAwareTrait.php)行为，也就是可以通过`setMapper(callable $mapper)`来增加数据转换过滤

实例代码

```php
//拼接一个查询
$q = JtInvoiceSchema::query()
    ->in(JtInvoiceSchema::FLD_UID, [214, 215, 123])
    ->order(JtInvoiceSchema::FLD_AMOUNT, 'DESC')
;

//建立一个QueryListProvider，每页5条
$list = new QueryListProvider($q, 5);
//设置mapper处理包装数据
$list->setMapper(function(JtInvoiceModel $invoice) {
    return [
        'xx' => 'yy',
        'd' => $invoice,
    ];
});

//返回
return [$list->getPager(), $list->fetch(1)];
```

返回结果

```json
[
  {
    "count": 3,
    "perPage": 5,
    "maxPage": 1
  },
  [
    {
      "xx": "yy",
      "d": {
        "invoiceId": "91",
        "orderId": "123",
        "uid": "214",
        "amount": "8.00000000000000000000000000000000000000000000000000",
        "status": "1",
        "serialNo": null,
        "paySerialNo": null,
        "detail": "[]",
        "expireTime": "1431605159",
        "version": "1",
        "createdTime": "1431601559",
        "updatedTime": "1431601559"
      }
    },
    {
      "xx": "yy",
      "d": {
        "invoiceId": "92",
        "orderId": "123",
        "uid": "214",
        "amount": "8.00000000000000000000000000000000000000000000000000",
        "status": "2",
        "serialNo": "2015051400000028",
        "paySerialNo": "2015051401766568",
        "detail": "{\"request\":[{\"ExecCode\":\"000000\",\"ExecMsg\":\"\\u5355\\u7b14\\u4ee3\\u6536\\u63d0\\u4ea4\\u6210\\u529f\",\"PaySerialNo\":\"2015051401766568\"},{\"Version\":\"1.0\",\"TransCode\":\"NCPS0002\",\"TransDate\":\"20150514\",\"TransTime\":\"190834\",\"SerialNo\":\"2015051400000028\"}]}",
        "expireTime": "1431605159",
        "version": "1",
        "createdTime": "1431601560",
        "updatedTime": "1431601560"
      }
    },
    {
      "xx": "yy",
      "d": {
        "invoiceId": "181",
        "orderId": "123",
        "uid": "215",
        "amount": "6.00000000000000000000000000000000000000000000000000",
        "status": "2",
        "serialNo": "2015051500000069",
        "paySerialNo": "2015051501766713",
        "detail": "{\"request\":[{\"ExecCode\":\"000000\",\"ExecMsg\":\"\\u5355\\u7b14\\u4ee3\\u6536\\u63d0\\u4ea4\\u6210\\u529f\",\"PaySerialNo\":\"2015051501766713\"},{\"Version\":\"1.0\",\"TransCode\":\"NCPS0002\",\"TransDate\":\"20150515\",\"TransTime\":\"124602\",\"SerialNo\":\"2015051500000069\"}]}",
        "expireTime": "1431668601",
        "version": "2",
        "createdTime": "1431665001",
        "updatedTime": "1431665004"
      }
    }
  ]
]
```

通过一个已有的list实例，可以Join其他数据(`WHERE FLD_FK IN (xx,yy,zz)`)

代码实例

```php
$joinedList = $list->startJoin('root')//设置别名
    ->join(
        UserSchema::query()
            //设置外键field名和从已有list $row中获取ID的方法
            ->on(UserSchema::FLD_UID, function ($row) {
                return $row['d']->uid - 114;//乱七八糟的数据……
            })
            ->setAlias('user')//设置别名
//                ->setMultipleMode(true)
    );


return [$joinedList->getPager(), $joinedList->fetch(1)];
```

返回结果的item会以别名整理，同样也可以通过`setMapper`自行变形

```json
[
  {
    "count": 3,
    "perPage": 5,
    "maxPage": 1
  },
  [
    {
      "root": {
        "xx": "yy",
        "d": {
          "invoiceId": "91",
          "orderId": "123",
          "uid": "214",
          "amount": "8.00000000000000000000000000000000000000000000000000",
          "status": "1",
          "serialNo": null,
          "paySerialNo": null,
          "detail": "[]",
          "expireTime": "1431605159",
          "version": "1",
          "createdTime": "1431601559",
          "updatedTime": "1431601559"
        }
      },
      "user": {
        "uid": "100",
        "mobilePhone": "13764141151",
        "password": "3847e9d9ba8c0ae862bd381319bc7391e5c0ef2c",
        "salt": "42346b46",
        "idStatus": "0",
        "createdTime": "1431347137",
        "updatedTime": "1431347137"
      }
    },
    {
      "root": {
        "xx": "yy",
        "d": {
          "invoiceId": "92",
          "orderId": "123",
          "uid": "214",
          "amount": "8.00000000000000000000000000000000000000000000000000",
          "status": "2",
          "serialNo": "2015051400000028",
          "paySerialNo": "2015051401766568",
          "detail": "{\"request\":[{\"ExecCode\":\"000000\",\"ExecMsg\":\"\\u5355\\u7b14\\u4ee3\\u6536\\u63d0\\u4ea4\\u6210\\u529f\",\"PaySerialNo\":\"2015051401766568\"},{\"Version\":\"1.0\",\"TransCode\":\"NCPS0002\",\"TransDate\":\"20150514\",\"TransTime\":\"190834\",\"SerialNo\":\"2015051400000028\"}]}",
          "expireTime": "1431605159",
          "version": "1",
          "createdTime": "1431601560",
          "updatedTime": "1431601560"
        }
      },
      "user": {
        "uid": "100",
        "mobilePhone": "13764141151",
        "password": "3847e9d9ba8c0ae862bd381319bc7391e5c0ef2c",
        "salt": "42346b46",
        "idStatus": "0",
        "createdTime": "1431347137",
        "updatedTime": "1431347137"
      }
    },
    {
      "root": {
        "xx": "yy",
        "d": {
          "invoiceId": "181",
          "orderId": "123",
          "uid": "215",
          "amount": "6.00000000000000000000000000000000000000000000000000",
          "status": "2",
          "serialNo": "2015051500000069",
          "paySerialNo": "2015051501766713",
          "detail": "{\"request\":[{\"ExecCode\":\"000000\",\"ExecMsg\":\"\\u5355\\u7b14\\u4ee3\\u6536\\u63d0\\u4ea4\\u6210\\u529f\",\"PaySerialNo\":\"2015051501766713\"},{\"Version\":\"1.0\",\"TransCode\":\"NCPS0002\",\"TransDate\":\"20150515\",\"TransTime\":\"124602\",\"SerialNo\":\"2015051500000069\"}]}",
          "expireTime": "1431668601",
          "version": "2",
          "createdTime": "1431665001",
          "updatedTime": "1431665004"
        }
      },
      "user": false
    }
  ]
]
```

