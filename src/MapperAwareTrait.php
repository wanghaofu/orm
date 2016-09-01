<?php namespace King\Orm;

use King\Core\CoreFactory;
use King\Core\Exceptions\Trouble;

trait MapperAwareTrait
{
    /**
     * @var callable[]
     */
    protected $mappers = [];

    public function setMapper(callable $mapper)
    {
        if (!empty($this->mappers)) {
            throw new \Exception(__METHOD__ . '/' . __LINE__);
        }

        $this->mappers = [];
        $this->appendMappper($mapper);

        return $this;
    }

    public function appendMappper(callable $mapper)
    {
        $this->mappers[] = function () use ($mapper) {
            $args = func_get_args();
            try {
                return call_user_func_array($mapper, $args);
            } catch (Trouble $e) {
                throw $e;
            } catch (\Exception $e) {
                CoreFactory::instance()->logger()->notice(__METHOD__, [
                    'args' => $args,
                ]);

                return false;
            }
        };

        return $this;
    }

    protected function applyMapper($data)
    {
        foreach ($this->mappers as $mapper) {
            $data = array_filter(array_map($mapper, $data), function ($item) {
                return $item !== false;
            });
        }

        return $data;
    }
}
