
Daject简介
======
Daject是一个关系型数据库抽象模型，通过该模型，可以在不写任何SQL或写很少的SQL就能执行大多数数据库查询操作。Daject具有面向对象，跨数据库的优点，通过数据库驱动的支持，代码能够非常方便的在主流的各种关系型数据库之间迁移。


Daject初始化
======
为了能够正常使用Daject查询数据，我们需要先对Daject进行初始化。


Daject的数据模型
======
Daject定义了两种数据模型。
Record模型
Record模型是对单条数据记录的抽象，从记录的级别处理数据。
Record模型的实现是DajectRecordObject对象，以及DajectRecordBase的子类。
Table模型
Table模型是对一张数据表的抽象，从数据表的级别处理数据。
Table模型的实现是DajectTableObject对象，以及DajectTableBase的子类。
Record模型
构造函数
DajectRecordObject对象
$record = new DajectRecordObject($table_name,$pkeys_array);
继承于DajectRecordBase的对象
　　$recortd = new Example($pkeys_array);
判断记录是否存在
$exist = $record->exist();//判断记录是否存在
读取数据
$name= $record->name;//读取name字段
$data = $record->fetch();//读取所有字段
更新和创建数据
　　$record->price= 20; //将price字段的值修改为20
　　$record->save();//保存数据,如果记录（sn为1）不存在，则自动创建(sn等于1，price为20的）记录，如果数据存在，则直接将price修改为20
　　
　　提醒：$record->save()语句一般情况下可省略，因为在系统进行垃圾回收（$record变量生命周期结束)时,该语句会被自动调用，如果你想在$record变量生命周期结束前更新数据，则应该加入此行代码
Table模型
构造函数
DajectTableObject对象
　　$table = new DajectTableObject($table_name,$keys_array);
继承于DajectTableBase的对象
　　$table = new Example($keys_array);
　　
　　
　　
　　
　　
基本查询:CURD操作
======
select操作
　　$table->select();//选择所有符合条件的记录
　　$table->select(n);//选择符合条件的前10条记录
　　$table->select(n,offset);//跳过前offset条记录，选择后面符合条件的n条记录
delete操作
　　$table->delete();//删除所有符合条件的记录
　　$table->delete(n);//删除符合条件的前n条记录
insert操作
　　$data = array(‘name’=>’your name’,’age’=>20);
　　$table->insert($data);
update操作
　　$data = array(‘age’=>30);
　　$table->update($data);
　　
　　
　　
　　
　　
　　
高级查询
======
where过滤查询
  $table->where(array(‘age’=>20));//只选择age等于20的记录
  $table->where(‘age=%i’,20);//只选择age等于20的记录
  $table->where(‘name=%s’,’john’);//只选择name等于’john’的记录
