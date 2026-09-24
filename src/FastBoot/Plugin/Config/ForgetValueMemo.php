<?php
declare(strict_types=1);

namespace GraphCommerce\FastBoot\Plugin\Config;

/**
 * Empties the value memo after a mutable configuration set and after a switch of the
 * current store, the two events that change what a read with the current scope answers.
 */
class ForgetValueMemo
{
    public function __construct(
        private readonly ValueMemo $memo,
    ) {
    }

    /**
     * @param object $subject
     * @param mixed $result
     * @return mixed
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterSetValue(object $subject, $result)
    {
        $this->memo->forget();

        return $result;
    }

    /**
     * @param object $subject
     * @param mixed $result
     * @return mixed
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterSetCurrentStore(object $subject, $result)
    {
        $this->memo->forget();

        return $result;
    }
}
