<?php
/** @license MIT
 * Copyright 2018 J. King et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Lax\Parser\XML;

use JKingWeb\Lax\Person\Collection as PersonCollection;
use JKingWeb\Lax\Category\Collection as CategoryCollection;
use JKingWeb\Lax\Feed as FeedStruct;
use JKingWeb\Lax\Date;
use JKingWeb\Lax\Text;
use JKingWeb\Lax\Url;

class Feed implements \JKingWeb\Lax\Parser\Feed {
    use Construct;
    use Primitives\Construct;
    use Primitives\Feed;

    /** @var string */
    protected $data;
    /** @var string */
    protected $contentType;
    /** @var \JKingWeb\Lax\Url */
    protected $url;
    /** @var \DOMElement */
    protected $subject;
    /** @var \DOMXpath */
    protected $xpath;

    /** Constructs a parsed feed */
    public function __construct(string $data, string $contentType = "", string $url = "") {
        $this->data = $data;
        $this->contentType = $contentType;
        if (strlen($url ?? "")) {
            $this->url = new Url($url);
        }
    }

    /** Performs initialization of the instance */
    protected function init(FeedStruct $feed): FeedStruct {
        $this->document = new \DOMDocument();
        $this->document->loadXML($this->data, \LIBXML_BIGLINES | \LIBXML_COMPACT);
        $this->document->documentURI = (string) $this->url;
        $this->xpath = new XPath($this->document);
        $this->subject = $this->document->documentElement;
        $ns = $this->subject->namespaceURI;
        $name = $this->subject->localName;
        if (is_null($ns) && $name === "rss") {
            $this->subject = $this->fetchElement("channel") ?? $this->subject;
            $feed->format = "rss";
            $feed->version = $this->document->documentElement->getAttribute("version");
        } elseif ($ns === XPath::NS['rdf'] && $name === "RDF") {
            $feed->format = "rdf";
            $channel = $this->fetchElement("rss1:channel|rss0:channel");
            if ($channel) {
                $this->subject = $channel;
                $feed->version = ($channel->namespaceURI === XPath::NS['rss1']) ? "1.0" : "0.90";
            } else {
                $element = $this->fetchElement("rss1:item|rss0:item|rss1:image|rss0:image");
                if ($element) {
                    $feed->version = ($element->namespaceURI === XPath::NS['rss1']) ? "1.0" : "0.90";
                }
            }
        } elseif ($ns === XPath::NS['atom'] && $name === "feed") {
            $feed->format = "atom";
            $feed->version = "1.0";
        } else {
            throw new \Exception;
        }
        $feed->meta->url = $this->url;
        return $feed;
    }

    /** Parses the feed to extract data */
    public function parse(FeedStruct $feed = null): FeedStruct {
        $feed = $this->init($feed ?? new FeedStruct);
        $feed->meta->url = $this->url;
        //$feed->sched->expired = $this->getExpired();
        $feed->id = $this->getId();
        //$feed->lang = $this->getLang();
        //$feed->url = $this->getUrl();
        //$feed->link = $this->getLink();
        //$feed->title = $this->getTitle();
        //$feed->summary = $this->getSummary();
        //$feed->dateModified = $this->getDateModified();
        //$feed->icon = $this->getIcon();
        //$feed->image = $this->getImage();
        //$feed->people = $this->getPeople();
        //$feed->categories = $this->getCategories();
        //$feed->entries = $this->getEntries($feed);
        return $feed;
    }

    public function getId(): ?string {
        return $this->getIdAtom() ?? $this->getIdDC() ?? $this->getIdRss2() ?? "";
    }

    public function getUrl(): ?Url {
        return $this->getUrlAtom() ?? $this->getUrlRss1() ?? $this->getUrlPod() ?? $this->reqUrl;
    }

    public function getTitle(): ?Text {
        return $this->getTitleAtom() ?? $this->getTitleRss1() ?? $this->getTitleRss2() ?? $this->getTitleDC() ?? $this->getTitlePod() ?? "";
    }

    public function getLink(): ?Url {
        return $this->getLinkAtom() ?? $this->getLinkRss1() ?? $this->getLinkRss2() ?? "";
    }

    public function getSummary(): ?Text {
        // unlike most other data, Atom is not preferred, because Atom doesn't really have feed summaries
        return $this->getSummaryDC() ?? $this->getSummaryRss1() ?? $this->getSummaryRss2() ?? $this->getSummaryPod() ?? $this->getSummaryAtom() ?? "";
    }

    public function getCategories(): CategoryCollection {
        return $this->getCategoriesAtom() ?? $this->getCategoriesRss2() ?? $this->getCategoriesDC() ?? $this->getCategoriesPod() ?? new CategoryCollection;
    }

    public function getPeople(): PersonCollection {
        $authors = $this->getAuthorsAtom() ?? $this->getAuthorsDC() ?? $this->getAuthorsPod() ?? $this->getAuthorsRss2() ?? new PersonCollection;
        $contributors = $this->getContributorsAtom() ?? $this->getContributorsDC() ?? new PersonCollection;
        $editors = $this->getEditorsRss2() ?? new PersonCollection;
        $webmasters = $this->getWebmastersPod() ?? $this->getWebmastersRss2() ?? new PersonCollection;
        return $authors->merge($contributors, $editors, $webmasters);
    }

    public function getDateModified(): ?Date {
        return $this->getDateModifiedAtom() ?? $this->getDateModifiedDC() ?? $this->getDateModifiedRss2();
    }

    public function getEntries(FeedStruct $feed = null): array {
        return $this->getEntriesAtom() ?? $this->getEntriesRss1() ?? $this->getEntriesRss2() ?? [];
    }

    public function getExpired(): ?bool {
        return null;
    }

    public function getLang(): ?string {
        return null;
    }

    public function getIcon(): ?Url {
        return null;
    }

    public function getImage(): ?Url {
        return null;
    }
}
