<?php
/* Copyright (c) 1998-2009 ILIAS open source, Extended GPL, see docs/LICENSE */

include_once("./Services/Export/classes/class.ilXmlImporter.php");

/**
 * Importer class for pages
 *
 * @author Alex Killing <alex.killing@gmx.de>
 * @version $Id: $
 * @ingroup ModulesMediaPool
 */
class ilCOPageImporter extends ilXmlImporter
{
	/**
	 * Initialisation
	 */
	function init()
	{
		include_once("./Services/COPage/classes/class.ilCOPageDataSet.php");
		$this->ds = new ilCOPageDataSet();
		$this->ds->setDSPrefix("ds");
		$this->config = $this->getImport()->getConfig("Services/COPage");
	}
	
	
	/**
	 * Import XML
	 *
	 * @param
	 * @return
	 */
	function importXmlRepresentation($a_entity, $a_id, $a_xml, $a_mapping)
	{

		if ($a_entity == "pgtp")
		{
			include_once("./Services/DataSet/classes/class.ilDataSetImportParser.php");
			$parser = new ilDataSetImportParser($a_entity, $this->getSchemaVersion(),
				$a_xml, $this->ds, $a_mapping);
		}

		if ($a_entity == "pg")
		{
			$pg_id = $a_mapping->getMapping("Services/COPage", "pg", $a_id);

			if ($pg_id != "")
			{
				$id = explode(":", $pg_id);
				if (count($id) == 2)
				{
					include_once("./Services/COPage/classes/class.ilPageObjectFactory.php");

					while (substr($a_xml, 0, 11) == "<PageObject")
					{
						$l1 = strpos($a_xml, ">");

						$page_tag = "<?xml version='1.0'?> ".substr($a_xml, 0, $l1+1)."</PageObject>";
						$page_data = simplexml_load_string($page_tag);
						$lstr = $page_data['Language'];
						$p = strpos($a_xml, "</PageObject>") + 13;
						$next_xml = "<PageObject>".substr($a_xml, $l1+1, $p - $l1 -1);

						if ($this->config->getForceLanguage() != "")
						{
							$lstr = $this->config->getForceLanguage();
						}
						if ($lstr == "")
						{
							$lstr = "-";
						}
						if ($this->config->getUpdateIfExists() && ilPageObject::_exists($id[0], $id[1], $lstr))
						{
							$page = ilPageObjectFactory::getInstance($id[0], $id[1], 0, $lstr);
							$page->setImportMode(true);
							$page->setXMLContent($next_xml);
							$page->updateFromXML();
						}
						else
						{
							$new_page = ilPageObjectFactory::getInstance($id[0]);
							$new_page->setImportMode(true);
							$new_page->setId($id[1]);
							if ($lstr != "" && $lstr != "-")
							{
								$new_page->setLanguage($lstr);
							}
							$new_page->setXMLContent($next_xml);
							$new_page->setActive(true);
							if (array_key_exists("Active", $page_data))
							{
								$new_page->setActive($page_data["Active"]);
							}
							$new_page->setActivationStart($page_data["ActivationStart"]);
							$new_page->setActivationEnd($page_data["ActivationEnd"]);
							$new_page->setShowActivationInfo($page_data["ShowActivationInfo"]);
							$new_page->createFromXML();
						}

						$a_xml = substr($a_xml, $p);
						if ($lstr == "")
						{
							$lstr = "-";
						}
						$a_mapping->addMapping("Services/COPage", "pgl", $a_id.":".$lstr, $pg_id.":".$lstr);
					}
				}
			}
		}
	}

	/**
	 * Final processing
	 *
	 * @param	array		mapping array
	 */
	function finalProcessing($a_mapping)
	{
		$pages = $a_mapping->getMappingsOfEntity("Services/COPage", "pgl");
		$media_objects = $a_mapping->getMappingsOfEntity("Services/MediaObjects", "mob");
		$file_objects = $a_mapping->getMappingsOfEntity("Modules/File", "file");
		//if (count($media_objects) > 0 || count($file_objects) > 0)
		//{
			foreach ($pages as $p)
			{
				$id = explode(":", $p);
				if (count($id) == 3)
				{
					include_once("./Services/COPage/classes/class.ilPageObject.php");
					if (ilPageObject::_exists($id[0], $id[1], $id[2], true))
					{
						include_once("./Services/COPage/classes/class.ilPageObjectFactory.php");
						$new_page = ilPageObjectFactory::getInstance($id[0], $id[1], 0, $id[2]);
						$new_page->buildDom();
						$med = $new_page->resolveMediaAliases($media_objects, $this->config->getReuseOriginallyExportedMedia());
						$fil = $new_page->resolveFileItems($file_objects);
						$il = false;
						if (!$this->config->getSkipInternalLinkResolve())
						{
							$il = $new_page->resolveIntLinks();
						}
						if ($med || $fil || $il)
						{
							$new_page->update(false, true);
						}
					}
				}
			}
		//}
	}
}

?>