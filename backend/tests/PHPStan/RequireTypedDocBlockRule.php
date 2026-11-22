<?php

namespace Tests\PHPStan;

use PhpParser\Comment\Doc;
use PhpParser\Node;
use PhpParser\Node\Stmt\ClassConst;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\EnumCase;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\Property;
use PHPStan\Analyser\Scope;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTextNode;
use PHPStan\PhpDocParser\Lexer\Lexer;
use PHPStan\PhpDocParser\Parser\PhpDocParser;
use PHPStan\PhpDocParser\Parser\TokenIterator;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Règle Larastan du projet (21.01, « Commentaires ») : chaque élément PHP porte un
 * DocBlock qui le décrit et en précise les types.
 *
 * - classe, interface, trait, énumération nommés, constante de classe, cas
 *   d'énumération : un DocBlock avec une description ;
 * - propriété : une description et une balise `@var <type>` ;
 * - fonction et méthode : une description, une balise `@param <type> $nom` par
 *   paramètre et une balise `@return <type>` (sauf pour un constructeur).
 *
 * Les fermetures et fonctions fléchées en sont dispensées : elles se lisent dans
 * leur contexte. La cohérence entre types du DocBlock et types natifs est, elle,
 * vérifiée par Larastan lui-même.
 *
 * @implements Rule<Node>
 */
final class RequireTypedDocBlockRule implements Rule
{
	/** Identifiant des erreurs, pour les filtrer ou les ignorer au cas par cas. */
	private const IDENTIFIER = 'suivi.docBlock';

	/**
	 * Reçoit l'analyseur de DocBlock fourni par le conteneur de PHPStan.
	 *
	 * @param Lexer $lexer Découpe le DocBlock en jetons.
	 * @param PhpDocParser $parser Construit l'arbre du DocBlock.
	 */
	public function __construct(
		private readonly Lexer $lexer,
		private readonly PhpDocParser $parser,
	) {
	}

	/**
	 * Type de nœud examiné : tous, le tri se fait dans processNode().
	 *
	 * @return class-string<Node>
	 */
	public function getNodeType(): string
	{
		return Node::class;
	}

	/**
	 * Vérifie le DocBlock d'un élément documentable.
	 *
	 * @param Node $node Nœud de l'arbre syntaxique.
	 * @param Scope $scope Portée courante (inutilisée).
	 * @return list<IdentifierRuleError>
	 */
	public function processNode(Node $node, Scope $scope): array
	{
		return match (true) {
			$node instanceof ClassLike => $this->isAnonymous($node) ? [] : $this->checkDescribed($node, 'La classe '.$node->name?->toString()),
			$node instanceof ClassConst => $this->checkDescribed($node, 'La constante '.$node->consts[0]->name->toString()),
			$node instanceof EnumCase => $this->checkDescribed($node, 'Le cas '.$node->name->toString()),
			$node instanceof Property => $this->checkProperty($node),
			$node instanceof ClassMethod, $node instanceof Function_ => $this->checkFunction($node),
			default => [],
		};
	}

	/**
	 * Vrai pour une classe anonyme (une migration, par exemple) : l'en-tête du
	 * fichier la décrit déjà. PHPStan leur donne un nom et un attribut dédié.
	 *
	 * @param ClassLike $node Classe, interface, trait ou énumération.
	 * @return bool
	 */
	private function isAnonymous(ClassLike $node): bool
	{
		return $node->name === null || $node->getAttribute('anonymousClass') === true;
	}

	/**
	 * Exige un DocBlock avec une description.
	 *
	 * @param Node $node Élément à vérifier.
	 * @param string $label Désignation de l'élément dans le message d'erreur.
	 * @return list<IdentifierRuleError>
	 */
	private function checkDescribed(Node $node, string $label): array
	{
		$doc = $this->parse($node->getDocComment());

		return $doc !== null && $this->hasDescription($doc) ? [] : [$this->error($label.' n\'a pas de DocBlock avec une description.', $node)];
	}

	/**
	 * Exige une description et une balise `@var` typée sur une propriété.
	 *
	 * @param Property $node Déclaration de propriété.
	 * @return list<IdentifierRuleError>
	 */
	private function checkProperty(Property $node): array
	{
		$label = 'La propriété $'.$node->props[0]->name->toString();
		$doc = $this->parse($node->getDocComment());
		if ($doc === null || ! $this->hasDescription($doc)) {
			return [$this->error($label.' n\'a pas de DocBlock avec une description.', $node)];
		}

		return $doc->getVarTagValues() === [] ? [$this->error($label.' n\'a pas de balise @var typée.', $node)] : [];
	}

	/**
	 * Exige une description, une balise `@param` typée par paramètre et une balise
	 * `@return` typée (hors constructeur).
	 *
	 * @param ClassMethod|Function_ $node Méthode ou fonction.
	 * @return list<IdentifierRuleError>
	 */
	private function checkFunction(ClassMethod|Function_ $node): array
	{
		$name = $node->name->toString();
		$label = ($node instanceof ClassMethod ? 'La méthode ' : 'La fonction ').$name.'()';
		$doc = $this->parse($node->getDocComment());
		if ($doc === null || ! $this->hasDescription($doc)) {
			return [$this->error($label.' n\'a pas de DocBlock avec une description.', $node)];
		}

		$errors = [];
		$documented = array_map(fn ($tag) => ltrim($tag->parameterName, '$'), $doc->getParamTagValues());
		foreach ($node->getParams() as $param) {
			$paramName = $param->var instanceof Node\Expr\Variable && is_string($param->var->name) ? $param->var->name : '';
			if (! in_array($paramName, $documented, true)) {
				$errors[] = $this->error($label.' : le paramètre $'.$paramName.' n\'a pas de balise @param typée.', $node);
			}
		}

		if (strtolower($name) !== '__construct' && $doc->getReturnTagValues() === []) {
			$errors[] = $this->error($label.' n\'a pas de balise @return typée.', $node);
		}

		return $errors;
	}

	/**
	 * Analyse un DocBlock ; null si l'élément n'en a pas.
	 *
	 * @param Doc|null $comment DocBlock attaché à l'élément.
	 * @return PhpDocNode|null
	 */
	private function parse(?Doc $comment): ?PhpDocNode
	{
		if ($comment === null) {
			return null;
		}

		return $this->parser->parse(new TokenIterator($this->lexer->tokenize($comment->getText())));
	}

	/**
	 * Vrai si le DocBlock contient du texte hors balises.
	 *
	 * @param PhpDocNode $doc DocBlock analysé.
	 * @return bool
	 */
	private function hasDescription(PhpDocNode $doc): bool
	{
		foreach ($doc->children as $child) {
			if ($child instanceof PhpDocTextNode && trim($child->text) !== '') {
				return true;
			}
		}

		return false;
	}

	/**
	 * Construit une erreur identifiée, placée sur la ligne de l'élément.
	 *
	 * @param string $message Message en français.
	 * @param Node $node Élément en faute.
	 * @return IdentifierRuleError
	 */
	private function error(string $message, Node $node): IdentifierRuleError
	{
		return RuleErrorBuilder::message($message)->identifier(self::IDENTIFIER)->line($node->getStartLine())->build();
	}
}
