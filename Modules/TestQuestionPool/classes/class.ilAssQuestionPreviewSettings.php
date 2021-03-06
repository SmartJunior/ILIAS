<?php
/* Copyright (c) 1998-2013 ILIAS open source, Extended GPL, see docs/LICENSE */


/**
 * @author		Björn Heyser <bheyser@databay.de>
 * @version		$Id$
 *
 * @package     Modules/TestQuestionPool
 */
class ilAssQuestionPreviewSettings
{
	private $contextRefId = null;
	
	private $genericFeedbackEnabled = false;
	
	private $specificFeedbackEnabled = false;
	
	private $hintProvidingEnabled = false;
	
	private $bestSolutionEnabled = false;

	public function __construct($contextRefId)
	{
		$this->contextRefId = $contextRefId;
	}

	public function init()
	{
		if( $this->isTestRefId() )
		{
			$this->initSettingsWithTestObject();
		}
		else
		{
			$this->initSettingsFromPostParameters();
		}
	}
	
	private function isTestRefId()
	{
		$objectType = ilObject::_lookupType($this->contextRefId, true);
		
		return $objectType == 'tst';
	}
	
	private function initSettingsWithTestObject()
	{
		$testOBJ = ilObjectFactory::getInstanceByRefId($this->contextRefId);
		$testOBJ->loadFromDb();
		
		$this->setGenericFeedbackEnabled($testOBJ->getGenericAnswerFeedback());
		$this->setSpecificFeedbackEnabled($testOBJ->getSpecificAnswerFeedback());
		$this->setHintProvidingEnabled($testOBJ->isOfferingQuestionHintsEnabled());
		$this->setBestSolutionEnabled($testOBJ->getInstantFeedbackSolution());
	}

	private function initSettingsFromPostParameters()
	{
		// get from post or from toolbar instance if possible
		
		$this->setGenericFeedbackEnabled(true);
		$this->setSpecificFeedbackEnabled(true);
		$this->setHintProvidingEnabled(true);
		$this->setBestSolutionEnabled(true);
	}

	public function setContextRefId($contextRefId)
	{
		$this->contextRefId = $contextRefId;
	}

	public function getContextRefId()
	{
		return $this->contextRefId;
	}

	public function setGenericFeedbackEnabled($genericFeedbackEnabled)
	{
		$this->genericFeedbackEnabled = $genericFeedbackEnabled;
	}

	public function isGenericFeedbackEnabled()
	{
		return $this->genericFeedbackEnabled;
	}

	public function setSpecificFeedbackEnabled($specificFeedbackEnabled)
	{
		$this->specificFeedbackEnabled = $specificFeedbackEnabled;
	}

	public function isSpecificFeedbackEnabled()
	{
		return $this->specificFeedbackEnabled;
	}

	public function setHintProvidingEnabled($hintProvidingEnabled)
	{
		$this->hintProvidingEnabled = $hintProvidingEnabled;
	}

	public function isHintProvidingEnabled()
	{
		return $this->hintProvidingEnabled;
	}

	public function setBestSolutionEnabled($bestSolutionEnabled)
	{
		$this->bestSolutionEnabled = $bestSolutionEnabled;
	}

	public function isBestSolutionEnabled()
	{
		return $this->bestSolutionEnabled;
	}

	public function isInstantFeedbackNavigationRequired()
	{
		if( $this->isGenericFeedbackEnabled() )
		{
			return true;
		}

		if( $this->isSpecificFeedbackEnabled() )
		{
			return true;
		}

		if( $this->isBestSolutionEnabled() )
		{
			return true;
		}

		return false;
	}

	public function isHintProvidingNavigationRequired()
	{
		return $this->isHintProvidingEnabled();
	}
}