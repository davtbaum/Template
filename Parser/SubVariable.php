<?php

/*
* This file is part of Spoon Library.
*
* (c) Davy Hellemans <davy@spoon-library.com>
*
* For the full copyright and license information, please view the license
* file that was distributed with this source code.
*/

namespace Spoon\Template\Parser;
use Spoon\Template\Environment;
use Spoon\Template\SyntaxError;
use Spoon\Template\TokenStream;
use Spoon\Template\Token;

/**
 * Class used to convert some tokens related subvariables into PHP code. The rules for subvariables
 * are a lot more restrictive than regular variables.
 *
 * @author Davy Hellemans <davy@spoon-library.com>
 */
class SubVariable
{
	/**
	 * @var Spoon\Template\Environment
	 */
	protected $environment;

	/**
	 * @var Spoon\Template\TokenStream
	 */
	protected $stream;

	/**
	 * List of chunks for this variable.
	 *
	 * @var array
	 */
	protected $variable = array();

	/**
	 * @param Spoon\Template\TokenStream $stream
	 * @param Spoon\Template\Environment $environment
	 */
	public function __construct(TokenStream $stream, Environment $environment)
	{
		$this->stream = $stream;
		$this->environment = $environment;
	}

	/**
	 * Builds the variable string.
	 *
	 * @return string
	 */
	protected function build()
	{
		$output = '$this->getVar($context, array(';
		$count = count($this->variable);

		// scan each chunk
		foreach($this->variable as $key => $value)
		{
			$output .= "'" . $value . "'";

			// last key
			$output .= ($key < $count - 1) ? ', ' : ')';
		}

		return $output . ')';
	}

	/**
	 * Compile the first variable you come across and return its PHP code string value.
	 *
	 * @return string
	 */
	public function compile()
	{
		$this->processName();
		return $this->build();
	}

	/**
	 * Processes each key element based on a set of allowed rules.
	 */
	protected function processKey()
	{
		// skip the "."
		$token = $this->stream->next();

		// the next part needs to be a name or number
		if(!$token->test(Token::NAME) && !$token->test(Token::NUMBER))
		{
			// @todo throw syntax error
		}

		// the lexer doesn't catch keys starting with a "$"
		if(strpos($token->getValue(), '$') !== false)
		{
			throw new SyntaxError(
				'Variable keys may not start with "$"',
				$token->getLine(),
				$this->stream->getFilename()
			);
		}

		// add to list of keys
		$key = $token->getValue();
		$token = $this->stream->next();

		// allowed next values are "." or ")"
		$value = $token->getValue();

		// new key
		if($token->test(Token::PUNCTUATION, '.'))
		{
			$this->variable[] = $key;
			$this->processKey();
		}

		// end subvariable
		else
		{
			$this->variable[] = $key;
			$this->stream->expect(Token::PUNCTUATION, ')');
		}
	}

	/**
	 * Processes the first key element of variable. Different rules apply to the first part.
	 */
	protected function processName()
	{
		$token = $this->stream->getCurrent();

		// must be a name token
		$this->stream->expect(Token::NAME);
		$this->variable[] = substr($token->getValue(), 1);

		$token = $this->stream->next();

		/*
		 * Subvaribles are always used as arguments within modifiers or methods. Therefor we may
		 * assume that the only allowed characters are: "." or ")"
		 */
		$value = $token->getValue();

		// expecting keys
		if($token->test(Token::PUNCTUATION, '.'))
		{
			$this->processKey();
		}

		// only thing allowed now is the end of the variable
		else
		{
			$this->stream->expect(Token::PUNCTUATION, ')');
		}
	}
}
