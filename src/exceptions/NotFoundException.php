<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2016/3/18
 * Time: 11:05
 */

namespace slimExtend\exceptions;

/**
 * Class NotFoundException
 * @package slimExtend\exceptions
 */
class NotFoundException extends \RuntimeException
{
    public function __construct($msg = '', $code = 14, \Exception $previous = null)
    {
        parent::__construct($msg ? : 'target not found!!', $code, $previous);
    }
}