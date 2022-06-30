<?php

/**
* @package   s9e\catastrophiccolumns
* @copyright Copyright (c) 2019-2022 The s9e authors
* @license   http://www.opensource.org/licenses/mit-license.php The MIT License
*/
namespace s9e\catastrophiccolumns;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use s9e\TextFormatter\Parser;
use s9e\TextFormatter\Parser\Tag;

class listener implements EventSubscriberInterface
{
	public static function getSubscribedEvents()
	{
		return ['core.text_formatter_s9e_configure_after' => 'onConfigure'];
	}

	public function onConfigure($event)
	{
		$configurator = $event['configurator'];

		$colNames = [];
		foreach ($configurator->tags as $tagName => $tag)
		{
			if (!preg_match('(^COL\\d*$)', $tagName))
			{
				continue;
			}

			$tag->template = preg_replace_callback(
				'(<xsl:value-of select="@content(\\d+)"/>)',
				function ($m) use (&$colNames, $tagName)
				{
					$colName    = 'r:' . $tagName . '-' . $m[1];
					$colNames[] = $colName;

					return '<xsl:choose><xsl:when test="' . $colName . '"><xsl:apply-templates select="' . $colName . '"/></xsl:when><xsl:otherwise>' . $m[0] . '</xsl:otherwise></xsl:choose>';
				},
				$tag->template
			);

			$tag->filterChain->append(__CLASS__ . '::addColumns')
				->resetParameters()
				->addParameterByName('parser')
				->addParameterByName('tag')
				->addParameterByName('text');
		}

		foreach (array_unique($colNames) as $colName)
		{
			if (!isset($configurator->tags[$colName]))
			{
				$configurator->tags->add($colName);
			}
		}
	}

	public static function addColumns(Parser $parser, Tag $startTag, $text)
	{
		$endTag = $startTag->getEndTag();
		if (!$endTag)
		{
			return;
		}

		$startPos = $startTag->getPos() + $startTag->getLen();
		$endPos   = $endTag->getPos();
		$tagText  = substr($text, $startPos, $endPos - $startPos);

		foreach (explode('|', $tagText) as $i => $chunk)
		{
			$tagName   = 'r:' . $startTag->getName() . '-' . $i;
			$len       = strlen($chunk);
			$newTag    = $parser->addTagPair($tagName, $startPos, 0, $startPos + $len, 0);
			$startPos += 1 + $len;

			$startTag->cascadeInvalidationTo($newTag);
		}
	}
}