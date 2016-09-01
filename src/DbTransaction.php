<?php namespace King\Orm;

use King\Core\CoreFactory;

class DbTransaction
{
    private static $instance;
    /**
     * @var callable
     */
    private $logic;
    /**
     * @var callable[]
     */
    private $successCallbacks = [];

    /**
     * @inheritDoc
     */
    protected function __construct(callable $logic)
    {
        self::$instance = $this;
        $this->logic = $logic;
    }

    /**
     * 执行一个DB事务
     *
     * ---
     *
     * $logic回调的签名为
     * `function (DbTransaction $transaction)`
     * 参数的实例可以用来调用afterSuccess方法等
     *
     * $logic回调中抛异常即可中断并会滚DB事务（同时异常会原样抛出）
     * $logic回调的返回值会作为本方法的返回值传出来
     *
     * @param callable $logic 业务逻辑
     * @return mixed $logic的返回值
     * @throws \Exception $logic可能抛出的异常
     */
    public static function run(callable $logic)
    {
        $instance = new self($logic);

        return $instance->runTransaction();
    }

    /**
     * 当前是否在事务过程中
     *
     * @return bool
     */
    public static function inTransaction()
    {
        return CoreFactory::instance()->pdo()->inTransaction();
    }

    /**
     * 断言：当前在事务过程中（否则抛错）
     */
    public static function assertInTransaction()
    {
        if (!self::inTransaction()) {
            throw new \Exception(__METHOD__ . '/' . __LINE__);
        }
    }

    /**
     * 断言：当前不在事务过程中（否则抛错）
     */
    public static function assertNotInTransaction()
    {
        if (self::inTransaction()) {
            throw new \Exception(__METHOD__ . '/' . __LINE__);
        }
    }

    /**
     * 当前事务成功commit后，运行 $logic 回调
     * 不在事务中时会抛错
     *
     * @param callable $logic
     */
    public static function onSuccess(callable $logic)
    {
        self::assertInTransaction();
        self::$instance->afterSuccess($logic);
    }

    /**
     * 不在事务中时会立即执行 $logic
     * 在事务时，等成功commit后，才运行 $logic 回调
     *
     * @param callable $logic
     */
    public static function waitOrRun(callable $logic)
    {
        if (self::inTransaction()) {
            self::$instance->afterSuccess($logic);
        } else {
            call_user_func($logic);
        }
    }

    /**
     * 当本次事务成功commit后，运行 $logic 回调
     * @param callable $logic
     */
    public function afterSuccess(callable $logic)
    {
        $this->successCallbacks[] = $logic;
    }

    protected function runTransaction()
    {
        self::assertNotInTransaction();

        $pdo = CoreFactory::instance()->pdo();
        $pdo->beginTransaction();

        try {
            $this->successCallbacks = [];
            $result = call_user_func($this->logic, $this);

            if (!$pdo->commit()) {
                throw new \Exception(__METHOD__ . '/' . __LINE__);
            }
        } catch (\Exception $e) {
            $pdo->rollBack();

            CoreFactory::instance()->logger()->info(__METHOD__, [
                'exception' => $e,
            ]);

            throw $e;
        }

        while ($callback = array_pop($this->successCallbacks)) {
            call_user_func($callback);
        }

        return $result;
    }
}
