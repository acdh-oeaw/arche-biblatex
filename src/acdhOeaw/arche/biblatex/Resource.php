<?php

/*
 * The MIT License
 *
 * Copyright 2021 Austrian Centre for Digital Humanities.
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

namespace acdhOeaw\arche\biblatex;

use RuntimeException;
use Psr\Log\LoggerInterface;
use RenanBr\BibTexParser\Listener as BiblatexL;
use RenanBr\BibTexParser\Parser as BiblatexP;
use RenanBr\BibTexParser\Processor\TagNameCaseProcessor as BiblatexCP;
use RenanBr\BibTexParser\Exception\ParserException as BiblatexE1;
use RenanBr\BibTexParser\Exception\ProcessorException as BiblatexE2;
use rdfInterface\DatasetInterface;
use rdfInterface\TermInterface;
use rdfInterface\LiteralInterface;
use quickRdf\DataFactory as DF;
use termTemplates\PredicateTemplate as PT;
use termTemplates\QuadTemplate as QT;
use termTemplates\LiteralTemplate as LT;
use acdhOeaw\arche\lib\Schema;
use acdhOeaw\arche\lib\RepoResourceInterface;
use acdhOeaw\arche\lib\dissCache\ResponseCacheItem;
use zozlak\logging\Log;
use zozlak\RdfConstants as RDF;

/**
 * Maps ARCHE resource metadata to a BibLaTeX bibliographic entry.
 * 
 * Fro BibTeX/BibLaTeX/CSL bibliographic entry reference see:
 * - https://www.bibtex.com/g/bibtex-format/#fields
 * - chapter 8 of http://tug.ctan.org/info/bibtex/tamethebeast/ttb_en.pdf
 * - https://mirror.kumi.systems/ctan/macros/latex/contrib/biblatex/doc/biblatex.pdf
 * - https://docs.citationstyles.org/en/stable/specification.html#appendix-iv-variables
 * 
 * @author zozlak
 */
class Resource {

    // for fields definitions see https://docs.citationstyles.org/en/stable/specification.html#appendix-iv-variables
    const CSL_SCHEMA_URL     = 'https://raw.githubusercontent.com/citation-style-language/schema/refs/heads/master/schemas/input/csl-data.json';
    const NO_OVERRIDE        = 'NOOVERRIDE';
    const TYPE_CONST         = 'const';
    const TYPE_PERSON        = 'person';
    const TYPE_CURRENT_DATE  = 'currentDate';
    const TYPE_DATE          = 'date';
    const TYPE_LITERAL       = 'literal';
    const TYPE_NOT_LINKED_ID = 'notLinkedId';
    const TYPE_URL           = 'url';
    const TYPE_ID            = 'id';
    const MIME_BIBLATEX      = 'application/x-bibtex';
    const MIME_CSL_JSON      = 'application/vnd.citationstyles.csl+json';
    const MIME_JSON          = 'application/json';

    /**
     * @param array<mixed> $param
     */
    static public function cacheHandler(RepoResourceInterface $res,
                                        array $param, object $config,
                                        ?LoggerInterface $log = null): ResponseCacheItem {

        $bibRes = new self($res, $config, $log);
        $format = match ($param[2] ?? '') {
            self::MIME_JSON => self::MIME_CSL_JSON,
            self::MIME_CSL_JSON => self::MIME_CSL_JSON,
            default => self::MIME_BIBLATEX,
        };
        unset($param[2]);
        $output = match ($format) {
            self::MIME_CSL_JSON => json_encode($bibRes->getCsl(...$param), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            default => $bibRes->getBiblatex(...$param),
        };
        return new ResponseCacheItem($output, 200, ['Content-Type' => $format]);
    }

    private RepoResourceInterface $res;
    private Schema $schema;
    private ?LoggerInterface $log = null;
    private object $config;
    private DatasetInterface $meta;
    private TermInterface $node;
    private string $lang;
    private object $mapping;

    public function __construct(RepoResourceInterface $res, object $config,
                                ?LoggerInterface $log = null) {
        $this->res    = $res;
        $this->schema = new Schema($config->schema);
        $this->config = $config;
        $this->log    = $log;
        $this->node   = $this->res->getUri();
        $this->meta   = $this->res->getGraph()->getDataset();
    }

    public function getCsl(string $lang, ?string $override = null,
                           ?object $mapping = null): array {
        $this->lang = $lang;

        if ($mapping !== null) {
            $this->mapping = $mapping;
        } else {
            $classes = $this->meta->listObjects(new QT($this->node, RDF::RDF_TYPE))->getValues();
            foreach ($classes as $c) {
                if (isset($this->config->mapping->$c)) {
                    $this->mapping = $this->config->mapping->$c;
                    break;
                }
            }
        }
        if (!isset($this->mapping)) {
            throw new RuntimeException("Repository resource is of unsupported class", 400);
        }

        $output = [];
        foreach ($this->mapping as $key => $definition) {
            $field = $this->formatProperty($key, $definition);
            if (!empty($field)) {
                $output[$key] = $field;
            }
        }

        // overrides from $.cfg.overrideProperty in metadata
        $this->applyOverrides($output);
        // overrides from $override parameter (e.g. from HTTP request parameter)
        if (!empty($override)) {
            $this->applyOverrides($output, $override);
        }

        return $output;
    }

    public function getBiblatex(string $lang, ?string $override = null): string {
        $csl       = $this->getCsl($lang, $override);
        $personFmt = function ($x): string {
            if (isset($x['literal'])) {
                return $x['literal'];
            }
            $ret = $x['family'] ?? '';
            if (!empty($ret) && !empty($x['given'])) {
                $ret .= ', ' . $x['given'];
            }
            return $ret;
        };

        $output = "@" . $this->csl2Biblatex('type', $csl['type']) . "{" . $csl['id'];
        foreach ($csl as $key => $value) {
            if (in_array($key, ['id', 'type'])) {
                continue;
            }
            $key = $this->csl2Biblatex('property', $key) ?? $key;
            if (is_array($value) && isset($value['raw'])) {
                // date
                $value = $value['raw'];
            } elseif (is_array($value)) {
                // persons
                $value = implode(' and ', array_map($personFmt, $value));
	    }
            $output .= ",\n  $key = {" . str_replace(["{", "}"], [' ', '', "\\{", "\\}"], $value) . "}";
        }
        $output .= "\n}\n";
        return $output;
    }

    /**
     * 
     * @param array<string, mixed> $fields
     */
    private function applyOverrides(array &$fields, ?string $override = null): void {
        $src      = $override === null ? 'metadata' : 'parameter';
        $override = trim((string) ($override ?? (string) $this->meta->getObject(new QT($this->node, $this->config->overrideProperty))));
        if (empty($override)) {
            return;
        }
        $this->log?->debug("Applying overrides from $src");
        $csl = json_decode($override, true);
        if (is_array($csl)) {
            $this->applyOverridesCsl($fields, $csl);
        } else {
            $this->applyOverridesBiblatex($fields, $override);
        }
    }

    /**
     * 
     * @param array<string, mixed> $fields
     * @param array<string, mixed> $csl
     */
    private function applyOverridesCsl(array &$fields, array $csl) {
        foreach ($csl as $key => $val) {
            // check if the key is valid
            $this->getCslPropertyType($key);
            $fields[$key] = $val;
        }
    }

    /**
     * 
     * @param array<string, mixed> $fields
     */
    private function applyOverridesBiblatex(array &$fields,
                                            ?string $biblatex = null): void {
        if (substr($biblatex, 0, 1) !== '@') {
            $biblatex = "@" . self::NO_OVERRIDE . "{" . self::NO_OVERRIDE . ",\n$biblatex\n}";
        }

        $listener = new BiblatexL();
        $listener->addProcessor(new BiblatexCP(CASE_LOWER));
        $parser   = new BiblatexP();
        $parser->addListener($listener);
        try {
            $parser->parseString($biblatex);
            $entries = $listener->export();
            if (count($entries) !== 1) {
                throw new RuntimeException("Exactly one BibLaTeX entry expected but " . count($entries) . " parsed: $biblatex");
            }
            foreach ($entries[0] as $key => $value) {
                if (!in_array($key, ['_type', '_original', 'citation-key', 'type'])) {
                    $key          = $this->biblatex2Csl('property', $key) ?? $key;
                    $type         = $this->getCslPropertyType($key);
                    $fields[$key] = match ($type) {
                        self::TYPE_DATE => ['raw' => $value],
                        self::TYPE_PERSON => $this->biblatexPersons2CslPersons($value),
                        default => $value,
                    };
                    $this->log?->debug("Overwriting field '$key' with '$value'");
                } elseif ($key === '_type' && $value !== self::NO_OVERRIDE) {
                    $fields['type'] = $this->biblatex2Csl('type', $value);
                    $this->log?->debug("Overwriting entry type with '$value'");
                } elseif ($key === 'citation-key' && $value !== self::NO_OVERRIDE) {
                    $fields['id'] = $value;
                    $this->log?->debug("Overwriting citation key with '$value'");
                }
            }
        } catch (BiblatexE1 $e) {
            $msg = $e->getMessage();
            throw new RuntimeException("Can't parse ($msg): $biblatex");
        } catch (BiblatexE2 $e) {
            $msg = $e->getMessage();
            throw new RuntimeException("Can't parse ($msg): $biblatex");
        }
    }

    /**
     * 
     * @return string|array<mixed>|null
     */
    private function formatProperty(string $key, mixed $definition): string | array | null {
        $cslPropType = $this->getCslPropertyType($key);

        // standardize the definition
        if (is_string($definition)) {
            $definition = ['properties' => [$definition]];
        } elseif (is_array($definition)) {
            $definition = ['properties' => $definition];
        }
        $definition = (object) $definition;

        // get values from other repository resource
        if (!empty($definition->srcClass) || !empty($definition->srcProperty)) {
            return $this->formatParent($key, $definition);
        }

        $nmsp     = $definition->reqNmsp ?? $definition->prefNmsp ?? null;
        $nmspReq  = isset($definition->reqNmsp);
        $srcProps = $definition->properties ?? [];
        $value    = match ($definition->type ?? $cslPropType) {
            self::TYPE_CONST => $definition->value,
            self::TYPE_LITERAL => $this->formatAll($srcProps),
            self::TYPE_DATE => $this->formatAll($srcProps),
            self::TYPE_PERSON => $this->formatPersons($srcProps),
            self::TYPE_ID => $this->formatKey($definition),
            self::TYPE_NOT_LINKED_ID => str_replace($nmsp, '', $this->formatAll($srcProps, null, true, $nmsp, $nmspReq)),
            self::TYPE_URL => $this->formatAll($srcProps, null, true, $nmsp, $nmspReq),
            self::TYPE_CURRENT_DATE => date('Y-m-d'),
            default => throw new RuntimeException('Unsupported property type ' . $definition->type, 500),
        };

        if ($cslPropType === self::TYPE_DATE) {
            $value = ['raw' => substr($value, 0, 10)];
	}
	if ($cslPropType === self::TYPE_LITERAL) {
            $value = str_replace(["\n", "\r"], [' ', ''], $value);
	}
        // corner cases
        if ($key === 'language') {
            $value = mb_strtoupper(substr($value, 0, 2));
        }
        return $value;
    }

    private function formatKey(object $keyCfg): string {
        $surname = new PT($this->config->mapping->person->family);
        $actors  = [];
        foreach ($keyCfg->actors as $property) {
            $tmpl = new QT($this->node, $property);
            foreach ($this->meta->listObjects($tmpl) as $actor) {
                $actors[] = $this->getLiteral($surname->withSubject($actor));
            }
            if (count($actors) > 0) {
                break;
            }
        }
        if (count($actors) > $keyCfg->maxActors) {
            $actors = $actors[0] . '_' . $this->config->etal;
        } else {
            $actors = join('_', $actors);
        }
        $year = substr((string) $this->getLiteral(new PT($keyCfg->year)), 0, 4);
        $id   = preg_replace('|^.*/|', '', (string) $this->node);
        $key  = $actors . '_' . $year . '_' . $id;
        return preg_replace('/[^-a-zA-Z0-9_]/', '', $key);
    }

    private function getLiteral(QT | PT $tmpl): ?string {
        if ($tmpl instanceof PT || $tmpl->getSubject() === null) {
            $tmpl = $tmpl->withSubject($this->node);
        }
        $tmplLang = $tmpl->withObject(new LT(null, LT::ANY, $this->lang));
        $tmplUnd  = $tmpl->withObject(new LT(null, LT::ANY, 'und'));
        $tmplAny  = $tmpl->withObject(new LT(null, LT::ANY));
        $value    = $this->meta->getObject($tmplLang) ?? ($this->meta->getObject($tmplUnd) ?? $this->meta->getObject($tmplAny));
        return $value !== null ? (string) $value : null;
    }

    /**
     * Gathers all values of given properties.
     * 
     * If a given property has at least one literal value in the preferred language,
     * literal values in other language are discarded.
     * 
     * Empty values are discarded.
     * 
     * @param array<string> $properties
     * @param TermInterface|null $resource
     * @param bool $onlyUrl
     * @param string | null $nmsp
     * @param bool $reqNmsp
     * @return string
     */
    private function formatAll(array $properties,
                               TermInterface | null $resource = null,
                               bool $onlyUrl = false, ?string $nmsp = null,
                               bool $reqNmsp = false): string {
        $tmpl      = new QT($resource ?? $this->node);
        $literals  = [];
        $resources = [];
        $values    = [];
        foreach ($properties as $property) {
            foreach ($this->meta->listObjects($tmpl->withPredicate($property)) as $i) {
                if ($i instanceof LiteralInterface) {
                    $value = (string) $i;
                    if (!empty($value)) {
                        $lang = (string) $i->getLang();
                        if (!isset($literals[$lang])) {
                            $literals[$lang] = [];
                        }
                        $values[$lang][] = $value;
                    }
                } else {
                    if ($onlyUrl) {
                        $value = (string) $i;
                    } else {
                        $value = $this->getLiteral(new QT($i, $this->schema->label));
                    }
                    if (!empty($value)) {
                        $resources[] = $value;
                    }
                }
            }
        }
        if (isset($values[$this->lang])) {
            $values = $values[$this->lang];
        } else {
            $values = array_merge(...array_values($values));
        }
        $values = array_unique(array_merge($resources, $values));
        if ($nmsp !== null) {
            $n = strlen($nmsp);
            foreach ($values as $i) {
                if (substr($i, 0, $n) === $nmsp) {
                    return $i;
                }
            }
            return count($values) > 0 && !$reqNmsp ? $values[0] : '';
        }
        sort($values);
        return join(', ', $values);
    }

    /**
     * 
     * @param array<string> $properties
     * @param TermInterface|string|null $resource
     * @return array<array<string, string>>
     */
    private function formatPersons(array $properties,
                                   TermInterface | string | null $resource = null): array {
        $tmpl    = new QT($resource ?? $this->node);
        $persons = [];
        foreach ($properties as $property) {
            foreach ($this->meta->listObjects($tmpl->withPredicate($property)) as $person) {
                $pid = (string) $person;
                if (!isset($persons[$pid])) {
                    $persons[$pid] = $this->formatPerson($person);
                }
            }
        }
        $sortStr = fn($x) => trim(($x['family'] ?? '') . ' ' . ($x['given'] ?? '') . ' ' . ($x['literal'] ?? ''));
        usort($persons, fn($a, $b) => $sortStr($a) <=> $sortStr($b));
        return $persons;
    }

    /**
     * 
     * @return array<string, string>
     */
    private function formatPerson(TermInterface $person): array {
        $cfg    = (array) $this->config->mapping->person;
        $tmpl   = new QT($person);
        $person = [];
        foreach ($cfg as $key => $prop) {
            $value = $this->getLiteral($tmpl->withPredicate($prop));
            if (!empty($value)) {
                $person[$key] = $value;
            }
        }
        if (count($person) > 1) {
            unset($person['literal']);
        }
        return $person;
    }

    private function formatParent(string $key, object $definition): string | array | null {
        $classTmpl  = new QT(null, DF::namedNode(RDF::RDF_TYPE));
        $parentTmpl = new QT($this->node, $definition->srcProperty ?? $this->schema->parent);
        $srcClass   = $definition->srcClass ?? '';
        $class      = null;
        do {
            $parent = $this->meta->getObject($parentTmpl);
            $class  = $this->meta->getObjectValue($classTmpl->withSubject($parent));
            if ($parent !== null) {
                $parentTmpl = $parentTmpl->withSubject($parent);
            }
        } while ($parent !== null && ($class !== $srcClass || empty($srcClass)));
        if ($this->node->equals($parentTmpl->getSubject())) {
            return null;
        }
        $citationRes       = clone $this;
        $citationRes->node = $parentTmpl->getSubject();
        if (!empty($definition->property)) {
            $mapping = (object) [$definition->property => $this->mapping->{$definition->property}];
        } else {
            $parentDef = clone $definition;
            unset($parentDef->srcProperty);
            unset($parentDef->srcClass);
            $mapping   = (object) [$key => $parentDef];
        }
        $csl = $citationRes->getCsl($this->lang, null, $mapping);
        return reset($csl) ?: null;
    }

    private function escapeBiblatex(string $value): string {
        return $value; // it seems that most important clients, like citation.js, anyway don't support any form of escaping
    }

    private function getCslPropertyType(string $property): string {
        $cslSchemaPath = __DIR__ . '/csl-schema.json';
        if (!file_exists($cslSchemaPath)) {
            file_put_contents($cslSchemaPath, file_get_contents(self::CSL_SCHEMA_URL));
        }
        $cslSchema  = json_decode(file_get_contents($cslSchemaPath), true);
        $properties = $cslSchema['items']['properties'];
        if (!isset($properties[$property])) {
            throw new RuntimeException("Property $property is not a part of the CSL-JSON schema");
        }
        $def  = $properties[$property];
        $type = $def['type'] ?? $def['$ref'];
        if (is_array($type) && in_array('string', $type)) {
            $type = 'string';
        } elseif ($type === 'array') {
            $type = $def['items']['$ref'];
        }
        return match ($type) {
            'string' => self::TYPE_LITERAL,
            '#/definitions/date-variable' => self::TYPE_DATE,
            '#/definitions/name-variable' => self::TYPE_PERSON,
            default => throw new RuntimeException("Unknown property $property type: $type " . print_r($def, true)),
        };
    }

    private function csl2Biblatex(string $dict, string $val): string | null {
        return $this->config->cslToBiblatex->$dict?->$val ?? null;
    }

    private function biblatex2Csl(string $dict, string $val): string | null {
        return $this->config->biblatexToCsl->$dict?->$val ?? null;
    }

    /**
     * 
     * @param string $src
     * @return array<array<string, string>>
     */
    private function biblatexPersons2CslPersons(string $src): array {
        $ret = explode(' and ', $src);
        $ret = array_map(fn($x) => explode(', ', $x), $ret);
        $ret = array_map(fn($x) => count($x) === 1 ? ['literal' => trim(x[0])] : [
            'family' => trim($x[0]), 'given'  => trim($x[1])], $ret);
        return $ret;
    }
}
