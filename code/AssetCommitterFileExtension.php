<?php

/**
 * Class AssetCommitterFileExtension
 *
 * @property AssetCommitterFileExtension|File $owner
 */
class AssetCommitterFileExtension extends Extension
{
	/**
	 * Commits a new file.
	 *
	 * @throws AssetCommitterFactoryException
	 */
	public function onAfterUpload()
	{
		if ($this->isFolder()) return; // Folder management is not supported at the moment.
		if ($this->isRepetitiveCall(__METHOD__)) return; // A dirty bug fix

		// We do not know whether the uploaded file is a completely new file or if it has replaced an older file. As SilverStripe
		// does not pass us that information, we need to take an enlightened guess by checking when the file db record was
		// first created.
		// TODO: See if SilverStripe 4 provides an indication whether the file was overridden.
		$created = strtotime($this->owner->Created);
		if ((time() - $created) > 10) // This is not a nice way to do this :(
		{
			// The creation time is older than ten seconds, so we are probably overwriting an existing file
			$this->committer()->CommitFileReplacement($this->owner);
		}
		else
		{
			// We are probably creating a new file
			$this->committer()->CommitFileCreation($this->owner);
		}
	}

	/**
	 * Commits a file deletion.
	 *
	 * @throws AssetCommitterFactoryException
	 */
	public function onAfterDelete()
	{
		if ($this->isFolder()) return; // Folder management is not supported at the moment.
		if ($this->isRepetitiveCall(__METHOD__)) return; // A dirty bug fix

		$this->committer()->CommitFileDeletion($this->owner);
	}

	/**
	 * Commits a renamed/moved file.
	 *
	 * @throws AssetCommitterFactoryException
	 */
	public function updateLinks($old_name, $new_name)
	{
		if ($this->isFolder()) return; // Folder management is not supported at the moment.
		if ($this->isRepetitiveCall(__METHOD__, $old_name, $new_name)) return; // A dirty bug fix

		$this->committer()->CommitFileRenaming($this->owner, $old_name, $new_name);
	}

	/**
	 * Helps to prevent doing any commits on folders, as this module is currently only designed for single file commits
	 * only. If a folder gets renamed, it should trigger separate commits for each file in it (for now). Same for deletion.
	 *
	 * @return bool
	 */
	private function isFolder()
	{
		return $this->owner instanceof Folder;
	}

	/**
	 * @return AssetCommitterInterface
	 * @throws AssetCommitterFactoryException
	 */
	private function committer()
	{
		return AssetCommitterFactory::getCommitter();
	}

	/**
	 * Sometimes SilverStripe calls hook methods multiple times in a row for an unknown reason. This method is a hack that
	 * tries to detect if a method call is redundant.
	 *
	 * At least SilverStripe 3 seems to do this for the updateLinks() method of this class. I haven't tested this in SS 4,
	 * nor have I tested this with the other hook methods of this class. But I will use this method in the other hook methods
	 * too just in case.
	 *
	 * TODO: Test if the bug also appears in SilverStripe 4
	 *
	 * @param $method
	 * @param mixed ...$parameters
	 * @return bool
	 */
	private function isRepetitiveCall($method, ... $parameters)
	{
		// Check if the method has been called at all in the past
		if (isset($this->method_calls[$method]))
		{
			// It has been called in the past. Check if the parameters have been the same as now.
			$method_call_parameter_sets = $this->method_calls[$method];
			foreach ($method_call_parameter_sets as $method_call_parameter_set)
			{
				if ($method_call_parameter_set === $parameters)
				{
					// The arrays have the same key/value pairs in the same order and of the same types.
					// In other words: a previous method call has used exactly the same parameters as the current method call uses.

					// Try to leave a trace about a method being called twice. The callers of this method will just silently exit when this method returns true, and won't log anything by themselves.
					Debug::message("Method $method is called repetitively with the same parameters: ".print_r($parameters, true), E_USER_NOTICE);
					return true;
				}
			}
		}
		else
		{
			// The method has not been called in the past at all
			// Prepare an array for call records
			$this->method_calls[$method] = [];
		}
		// The method either has not been called in the past, or if it has, at least not with the same parameters as now

		// Record the current call
		$this->method_calls[$method][] = $parameters;

		return false;
	}
	private $method_calls = [];
}
