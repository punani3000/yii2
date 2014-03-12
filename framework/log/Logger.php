<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\log;

use Yii;
use yii\base\Component;

/**
 * ����Ϣ��¼�ڴ洢���в�������Ҫ�����Ƿ��͵���ͬ��Ŀ��.
 *
 * Logger is registered as a core application component������ʹ��`Yii::$app->log`����.
 * ͨ��[[log()]]������¼һ����־��Ϣ. Ϊ�˷���,
 * [[Yii]] �� �ṩ��һϵ���й��� ������־�ķ���:
 *
 * - [[Yii::trace()]]
 * - [[Yii::error()]]
 * - [[Yii::warning()]]
 * - [[Yii::info()]]
 * - [[Yii::beginProfile()]]
 * - [[Yii::endProfile()]]
 *
 * ���㹻����Ϣ���ۻ��ڼ�¼��, �򵱵�ǰ�������,
 * ��¼����Ϣ�������͵���ͬ��[[targets]], ������־�ļ�, �����ʼ�.
 *
 * ������ͨ��Ӧ�ó�����������Ŀ��, ��������:
 *
 * ~~~
 * [
 *     'components' => [
 *         'log' => [
 *             'targets' => [
 *                 'file' => [
 *                     'class' => 'yii\log\FileTarget',
 *                     'levels' => ['trace', 'info'],
 *                     'categories' => ['yii\*'],
 *                 ],
 *                 'email' => [
 *                     'class' => 'yii\log\EmailTarget',
 *                     'levels' => ['error', 'warning'],
 *                     'message' => [
 *                         'to' => 'admin@example.com',
 *                     ],
 *                 ],
 *             ],
 *         ],
 *     ],
 * ]
 * ~~~
 *
 * ÿ����־���������һ�����ƣ�����ͨ��[[targets]]��������
 * ����:
 *
 * ~~~
 * Yii::$app->log->targets['file']->enabled = false;
 * ~~~
 *
 * ��Ӧ�ó��������[[flushInterval]]����, ��¼��������[[flush()]]
 * ���ͼ�¼����Ϣ����ͬ����־Ŀ��, �����ļ�, email, Web.
 *
 * @property array $dbProfiling ��һ��Ԫ��ָʾִ�е�SQL��������, 
 * �ڶ���Ԫ�ص���ʱ�仨����SQLִ��. ���������ֻ����.
 * @property float $elapsedTime ��ʱ�䣬��ǰ��������Ϊ��λ. 
 * ���������ֻ����.
 * @property array $profiling �����ý��. Each element is an array consisting of these elements:
 * `info`, `category`, `timestamp`, `trace`, `level`, `duration`. ��������ֻ����.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class Logger extends Component
{
	/**
	 * ������Ϣ����. Ӧ�ó����쳣��ֹ�Ĵ�����Ϣ
	 * ���ҿ�����Ҫ�����ߵĴ���.
	 */
	const LEVEL_ERROR = 0x01;
	/**
	 * ������Ϣ����. ����������������ľ�����Ϣ
	 * ��Ӧ�ó����ܹ���������. ������ԱӦ��ע������Ϣ.
	 */
	const LEVEL_WARNING = 0x02;
	/**
	 * �ο���Ϣ����. An informational message is one that includes certain information
	 * for developers to review.
	 */
	const LEVEL_INFO = 0x04;
	/**
	 * ������Ϣ����. ����Ϣ��ʾ�˴���ִ������.
	 */
	const LEVEL_TRACE = 0x08;
	/**
	 * ������Ϣ����. This indicates the message is for profiling purpose.
	 */
	const LEVEL_PROFILE = 0x40;
	/**
	 * ������Ϣ����. This indicates the message is for profiling purpose. ����־��
	 * һ��������Ŀ�ʼ.
	 */
	const LEVEL_PROFILE_BEGIN = 0x50;
	/**
	 * ������Ϣ����. This indicates the message is for profiling purpose. ����־��
	 * һ��������Ľ���.
	 */
	const LEVEL_PROFILE_END = 0x60;


	/**
	 * @var array ��¼����Ϣ. ������ͨ��[[log()]] �� [[flush()]]����.
	 * ÿ����־��Ϣ�������½ṹ:
	 *
	 * ~~~
	 * [
	 *   [0] => message (mixed, can be a string or some complex data, such as an exception object)
	 *   [1] => level (integer)
	 *   [2] => category (string)
	 *   [3] => timestamp (float, obtained by microtime(true))
	 *   [4] => traces (array, debug backtrace, contains the application code call stacks)
	 * ]
	 * ~~~
	 */
	public $messages = [];
	/**
	 * @var array ��������. ���������ڴ洢�������͵ĵ�������,������
	 * ��ͬ�ĵط�.
	 */
	public $data = [];
	/**
	 * @var array|Target[] ��־Ŀ��. Each array element represents a single [[Target|log target]] instance
	 * or the configuration for creating the log target instance.
	 */
	public $targets = [];
	/**
	 * @var integer how many messages should be logged before they are flushed from memory and sent to targets.
	 * Defaults to 1000, meaning the [[flush]] method will be invoked once every 1000 messages logged.
	 * Set this property to be 0 if you don't want to flush messages until the application terminates.
	 * This property mainly affects how much memory will be taken by the logged messages.
	 * A smaller value means less memory, but will increase the execution time due to the overhead of [[flush()]].
	 */
	public $flushInterval = 1000;
	/**
	 * @var integer how much call stack information (file name and line number) should be logged for each message.
	 * If it is greater than 0, at most that number of call stacks will be logged. Note that only application
	 * call stacks are counted.
	 *
	 * If not set, it will default to 3 when `YII_ENV` is set as "dev", and 0 otherwise.
	 */
	public $traceLevel;

	/**
	 * Initializes the logger by registering [[flush()]] as a shutdown function.
	 */
	public function init()
	{
		parent::init();
		if ($this->traceLevel === null) {
			$this->traceLevel = YII_ENV_DEV ? 3 : 0;
		}
		foreach ($this->targets as $name => $target) {
			if (!$target instanceof Target) {
				$this->targets[$name] = Yii::createObject($target);
			}
		}
		register_shutdown_function([$this, 'flush'], true);
	}

	/**
	 * ��¼���и������ͺ�������Ϣ.
	 * ���[[traceLevel]]����0, additional call stack information about
	 * the application code will be logged as well.
	 * @param string $message the message to be logged.
	 * @param integer $level the level of the message. This must be one of the following:
	 * `Logger::LEVEL_ERROR`, `Logger::LEVEL_WARNING`, `Logger::LEVEL_INFO`, `Logger::LEVEL_TRACE`,
	 * `Logger::LEVEL_PROFILE_BEGIN`, `Logger::LEVEL_PROFILE_END`.
	 * @param string $category ����Ϣ����.
	 */
	public function log($message, $level, $category = 'application')
	{
		$time = microtime(true);
		$traces = [];
		if ($this->traceLevel > 0) {
			$count = 0;
			$ts = debug_backtrace();
			array_pop($ts); // remove the last trace since it would be the entry script, not very useful
			foreach ($ts as $trace) {
				if (isset($trace['file'], $trace['line']) && strpos($trace['file'], YII_PATH) !== 0) {
					unset($trace['object'], $trace['args']);
					$traces[] = $trace;
					if (++$count >= $this->traceLevel) {
						break;
					}
				}
			}
		}
		$this->messages[] = [$message, $level, $category, $time, $traces];
		if ($this->flushInterval > 0 && count($this->messages) >= $this->flushInterval) {
			$this->flush();
		}
	}

	/**
	 * ���ڴ浽Ŀ��ˢ����־��Ϣ.
	 * @param boolean $final �Ƿ���һ�������ڼ��������.
	 */
	public function flush($final = false)
	{
		/** @var Target $target */
		foreach ($this->targets as $target) {
			if ($target->enabled) {
				$target->collect($this->messages, $final);
			}
		}
		$this->messages = [];
	}

	/**
	 * �����Ե�ǰ����Ŀ�ʼ��������ʱ��.
	 * ���ַ������㣬���ں����ļ�[[\yii\BaseYii]]��ʼ��
	 * ��`YII_BEGIN_TIME`�����ʱ��� 
	 * ֮��Ĳ�ͬ.
	 * @return float �ܵ�����ʱ�䣬����Ϊ��λ.
	 */
	public function getElapsedTime()
	{
		return microtime(true) - YII_BEGIN_TIME;
	}

	/**
	 * ���صķ������.
	 *
	 * Ĭ�����еķ��������������. ����ʹ��
	 * `$categories` and `$excludeCategories` ��Ϊ����������
	 * ������Ȥ�Ľ��.
	 *
	 * @param array $categories ������Ȥ������б�.
	 * You can use an asterisk at the end of a category to do a prefix match.
	 * For example, 'yii\db\*' will match categories starting with 'yii\db\',
	 * such as 'yii\db\Connection'.
	 * @param array $excludeCategories list of categories that you want to exclude
	 * @return array the profiling results. Each element is an array consisting of these elements:
	 * `info`, `category`, `timestamp`, `trace`, `level`, `duration`.
	 */
	public function getProfiling($categories = [], $excludeCategories = [])
	{
		$timings = $this->calculateTimings($this->messages);
		if (empty($categories) && empty($excludeCategories)) {
			return $timings;
		}

		foreach ($timings as $i => $timing) {
			$matched = empty($categories);
			foreach ($categories as $category) {
				$prefix = rtrim($category, '*');
				if (strpos($timing['category'], $prefix) === 0 && ($timing['category'] === $category || $prefix !== $category)) {
					$matched = true;
					break;
				}
			}

			if ($matched) {
				foreach ($excludeCategories as $category) {
					$prefix = rtrim($category, '*');
					foreach ($timings as $i => $timing) {
						if (strpos($timing['category'], $prefix) === 0 && ($timing['category'] === $category || $prefix !== $category)) {
							$matched = false;
							break;
						}
					}
				}
			}

			if (!$matched) {
				unset($timings[$i]);
			}
		}
		return array_values($timings);
	}

	/**
	 * �������ݿ��ѯ��ͳ�ƽ��.
	 * ���صĽ������ִ�е�SQL����������
	 * ���ѵ���ʱ��.
	 * @return array ��һ��Ԫ�ر�ʾִ�е�SQL��������,
	 * �ڶ���Ԫ����SQLִ�л��ѵ���ʱ��.
	 */
	public function getDbProfiling()
	{
		$timings = $this->getProfiling(['yii\db\Command::query', 'yii\db\Command::execute']);
		$count = count($timings);
		$time = 0;
		foreach ($timings as $timing) {
			$time += $timing['duration'];
		}
		return [$count, $time];
	}

	/**
	 * �����������־��Ϣ���õ�ʱ��.
	 * @param array $messages �ӷ����л�õ���־��Ϣ
	 * @return array timings. Each element is an array consisting of these elements:
	 * `info`, `category`, `timestamp`, `trace`, `level`, `duration`.
	 */
	public function calculateTimings($messages)
	{
		$timings = [];
		$stack = [];

		foreach ($messages as $i => $log) {
			list($token, $level, $category, $timestamp, $traces) = $log;
			$log[5] = $i;
			if ($level == Logger::LEVEL_PROFILE_BEGIN) {
				$stack[] = $log;
			} elseif ($level == Logger::LEVEL_PROFILE_END) {
				if (($last = array_pop($stack)) !== null && $last[0] === $token) {
					$timings[$last[5]] = [
						'info' => $last[0],
						'category' => $last[2],
						'timestamp' => $last[3],
						'trace' => $last[4],
						'level' => count($stack),
						'duration' => $timestamp - $last[3],
					];
				}
			}
		}

		ksort($timings);

		return array_values($timings);
	}


	/**
	 * ����ָ���������ı���ʾ.
	 * @param integer $level ��Ϣ����, ����. [[LEVEL_ERROR]], [[LEVEL_WARNING]].
	 * @return string �ü�����ı���ʾ
	 */
	public static function getLevelName($level)
	{
		static $levels = [
			self::LEVEL_ERROR => 'error',
			self::LEVEL_WARNING => 'warning',
			self::LEVEL_INFO => 'info',
			self::LEVEL_TRACE => 'trace',
			self::LEVEL_PROFILE_BEGIN => 'profile begin',
			self::LEVEL_PROFILE_END => 'profile end',
		];
		return isset($levels[$level]) ? $levels[$level] : 'unknown';
	}
}
