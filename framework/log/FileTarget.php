<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\log;

use Yii;
use yii\base\InvalidConfigException;
use yii\helpers\FileHelper;

/**
 * FileTarget ��һ���ļ��м�¼��־��Ϣ.
 *
 * ��־�ļ���ͨ��ָ�� [[logFile]]. �����־�ļ��Ĵ�С����
 * [[maxFileSize]] (ǧ�ֽ�), ��ִ����ת����, 
 * ʹ�ú�׺��'.1'��������ǰ��־�ļ�. �������е���־�ļ�
 * ����һ������ƶ�, i.e., '.2' to '.3', '.1' to '.2', �ȵ�.
 * [[maxLogFiles]] ������� ָ���˱������ļ���.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class FileTarget extends Target
{
	/**
	 * @var string ��־�ļ���·����·������. ���δ����, Ĭ��ʹ�� "@runtime/logs/app.log" �ļ���.
	 * ��������ڣ����Զ�����һ��������־�ļ���Ŀ¼.
	 */
	public $logFile;
	/**
	 * @var integer �����־�ļ���С, ��ǧ�ֽ�. Ĭ������Ϊ10240, �� 10MB.
	 */
	public $maxFileSize = 10240; // in KB
	/**
	 * @var integer ��ת��־�ļ���.Ĭ������Ϊ5.
	 */
	public $maxLogFiles = 5;
	/**
	 * @var integer ���´�������־�ļ�����Ȩ��.
	 * ���ֵ����PHP��chmod()����ʹ��. û��umask��ʹ��.
	 * ��δ����, ��Ȩ�޽��ɵ�ǰ��������.
	 */
	public $fileMode;
	/**
	 * @var integer ���´�����Ŀ¼����Ȩ��.
	 * ���ֵ����PHP��chmod()����ʹ��. û��umask��ʹ��.
	 * Ĭ������Ϊ0775, ����ζ�Ÿ�Ŀ¼�������߻����ǿɶ���д��,
	 * ���������û�ֻ��.
	 */
	public $dirMode = 0775;


	/**
	 * ��ʼ��·��.
	 * ����Ա����·��֮�󣬴˷���������.
	 */
	public function init()
	{
		parent::init();
		if ($this->logFile === null) {
			$this->logFile = Yii::$app->getRuntimePath() . '/logs/app.log';
		} else {
			$this->logFile = Yii::getAlias($this->logFile);
		}
		$logPath = dirname($this->logFile);
		if (!is_dir($logPath)) {
			FileHelper::createDirectory($logPath, $this->dirMode, true);
		}
		if ($this->maxLogFiles < 1) {
			$this->maxLogFiles = 1;
		}
		if ($this->maxFileSize < 1) {
			$this->maxFileSize = 1;
		}
	}

	/**
	 * д��־��Ϣ���ļ�.
	 * @throws InvalidConfigException ����޷��򿪲�д����־�ļ�
	 */
	public function export()
	{
		$text = implode("\n", array_map([$this, 'formatMessage'], $this->messages)) . "\n";
		if (($fp = @fopen($this->logFile, 'a')) === false) {
			throw new InvalidConfigException("Unable to append to log file: {$this->logFile}");
		}
		@flock($fp, LOCK_EX);
		if (@filesize($this->logFile) > $this->maxFileSize * 1024) {
			$this->rotateFiles();
			@flock($fp, LOCK_UN);
			@fclose($fp);
			@file_put_contents($this->logFile, $text, FILE_APPEND | LOCK_EX);
		} else {
			@fwrite($fp, $text);
			@flock($fp, LOCK_UN);
			@fclose($fp);
		}
		if ($this->fileMode !== null) {
			@chmod($this->logFile, $this->fileMode);
		}
	}

	/**
	 * ��ת��־�ļ�.
	 */
	protected function rotateFiles()
	{
		$file = $this->logFile;
		for ($i = $this->maxLogFiles; $i > 0; --$i) {
			$rotateFile = $file . '.' . $i;
			if (is_file($rotateFile)) {
				// suppress errors because it's possible multiple processes enter into this section
				if ($i === $this->maxLogFiles) {
					@unlink($rotateFile);
				} else {
					@rename($rotateFile, $file . '.' . ($i + 1));
				}
			}
		}
		if (is_file($file)) {
			@rename($file, $file . '.1'); // suppress errors because it's possible multiple processes enter into this section
		}
	}
}
