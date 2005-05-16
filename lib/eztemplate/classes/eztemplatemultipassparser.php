<?php
//
// Definition of eZTemplateMultiPassParser class
//
// Created on: <26-Nov-2002 17:25:44 amos>
//
// Copyright (C) 1999-2005 eZ systems as. All rights reserved.
//
// This source file is part of the eZ publish (tm) Open Source Content
// Management System.
//
// This file may be distributed and/or modified under the terms of the
// "GNU General Public License" version 2 as published by the Free
// Software Foundation and appearing in the file LICENSE included in
// the packaging of this file.
//
// Licencees holding a valid "eZ publish professional licence" version 2
// may use this file in accordance with the "eZ publish professional licence"
// version 2 Agreement provided with the Software.
//
// This file is provided AS IS with NO WARRANTY OF ANY KIND, INCLUDING
// THE WARRANTY OF DESIGN, MERCHANTABILITY AND FITNESS FOR A PARTICULAR
// PURPOSE.
//
// The "eZ publish professional licence" version 2 is available at
// http://ez.no/ez_publish/licences/professional/ and in the file
// PROFESSIONAL_LICENCE included in the packaging of this file.
// For pricing of this licence please contact us via e-mail to licence@ez.no.
// Further contact information is available at http://ez.no/company/contact/.
//
// The "GNU General Public License" (GPL) is available at
// http://www.gnu.org/copyleft/gpl.html.
//
// Contact licence@ez.no if any conditions of this licencing isn't clear to
// you.
//

/*! \file eztemplatemultipassparser.php
*/

/*!
  \class eZTemplateMultiPassParser eztemplatemultipassparser.php
  \brief The class eZTemplateMultiPassParser does

*/

include_once( 'lib/eztemplate/classes/eztemplateparser.php' );
include_once( 'lib/eztemplate/classes/eztemplateelementparser.php' );
include_once( 'lib/eztemplate/classes/eztemplate.php' );

class eZTemplateMultiPassParser extends eZTemplateParser
{
    /*!
     Constructor
    */
    function eZTemplateMultiPassParser()
    {
        $this->ElementParser = eZTemplateElementParser::instance();
    }


    /*!
     Parses the template file $sourceText. See the description of this class
     for more information on the parsing process.

    */
    function parse( &$tpl, &$sourceText, &$rootElement, $rootNamespace, &$resourceData )
    {
        $relatedResource = $resourceData['resource'];
        $relatedTemplateName = $resourceData['template-filename'];

        $currentRoot =& $rootElement;
        $leftDelimiter = $tpl->LDelim;
        $rightDelimiter = $tpl->RDelim;
        $sourceLength = strlen( $sourceText );
        $sourcePosition = 0;

        eZDebug::accumulatorStart( 'template_multi_parser_1', 'template_total', 'Template parser: create text elements' );
        $textElements =& $this->parseIntoTextElements( $tpl, $sourceText, $sourcePosition,
                                                       $leftDelimiter, $rightDelimiter, $sourceLength,
                                                       $relatedTemplateName );
        eZDebug::accumulatorStop( 'template_multi_parser_1' );

        eZDebug::accumulatorStart( 'template_multi_parser_2', 'template_total', 'Template parser: remove whitespace' );
        $textElements =& $this->parseWhitespaceRemoval( $tpl, $textElements );
        eZDebug::accumulatorStop( 'template_multi_parser_2' );

        eZDebug::accumulatorStart( 'template_multi_parser_3', 'template_total', 'Template parser: construct tree' );
        $this->parseIntoTree( $tpl, $textElements, $currentRoot,
                              $rootNamespace, $relatedResource, $relatedTemplateName );
        eZDebug::accumulatorStop( 'template_multi_parser_3' );
    }

    function gotoEndPosition( $text, $line, $column, &$endLine, &$endColumn )
    {
        $lines = preg_split( "#\r\n|\r|\n#", $text );
        if ( count( $lines ) > 0 )
        {
            $endLine = $line + count( $lines ) - 1;
            $lastLine = $lines[count($lines)-1];
            if ( count( $lines ) > 1 )
                $endColumn = strlen( $lastLine );
            else
                $endColumn = $column + strlen( $lastLine );
        }
        else
        {
            $endLine = $line;
            $endColumn = $column;
        }
    }

    function &parseIntoTextElements( &$tpl, $sourceText, $sourcePosition,
                                     $leftDelimiter, $rightDelimiter, $sourceLength,
                                     $relatedTemplateName )
    {
        if ( $tpl->ShowDetails )
            eZDebug::addTimingPoint( "Parse pass 1 (simple tag parsing)" );
        $currentLine = 1;
        $currentColumn = 0;
        $textElements = array();
        while( $sourcePosition < $sourceLength )
        {
            $tagPos = strpos( $sourceText, $leftDelimiter, $sourcePosition );
            if ( $tagPos === false )
            {
                // No more tags
                unset( $data );
                $data =& substr( $sourceText, $sourcePosition );
                $this->gotoEndPosition( $data, $currentLine, $currentColumn, $endLine, $endColumn );
                $textElements[] = array( "text" => $data,
                                         "type" => EZ_ELEMENT_TEXT,
                                         'placement' => array( 'templatefile' => $relatedTemplateName,
                                                               'start' => array( 'line' => $currentLine,
                                                                                 'column' => $currentColumn,
                                                                                 'position' => $sourcePosition ),
                                                               'stop' => array( 'line' => $endLine,
                                                                                'column' => $endColumn,
                                                                                'position' => $sourceLength - 1 ) ) );
                $sourcePosition = $sourceLength;
                $currentLine = $endLine;
                $currentColumn = $endColumn;
            }
            else
            {
                $blockStart = $tagPos;
                $tagPos++;
                if ( $tagPos < $sourceLength and
                     $sourceText[$tagPos] == "*" ) // Comment
                {
                    $endPos = strpos( $sourceText, "*$rightDelimiter", $tagPos + 1 );
                    $len = $endPos - $tagPos;
                    if ( $sourcePosition < $blockStart )
                    {
                        // Add text before tag.
                        unset( $data );
                        $data =& substr( $sourceText, $sourcePosition, $blockStart - $sourcePosition );
                        $this->gotoEndPosition( $data, $currentLine, $currentColumn, $endLine, $endColumn );
                        $textElements[] = array( "text" => $data,
                                                 "type" => EZ_ELEMENT_TEXT,
                                                 'placement' => array( 'templatefile' => $relatedTemplateName,
                                                                       'start' => array( 'line' => $currentLine,
                                                                                         'column' => $currentColumn,
                                                                                         'position' => $sourcePosition ),
                                                                       'stop' => array( 'line' => $endLine,
                                                                                        'column' => $endColumn,
                                                                                        'position' => $blockStart ) ) );
                        $currentLine = $endLine;
                        $currentColumn = $endColumn;
                    }
                    if ( $endPos === false )
                    {
                        $endPos = $sourceLength;
                        $blockEnd = $sourceLength;
                    }
                    else
                    {
                        $blockEnd = $endPos + 2;
                    }
                    $comment_text = substr( $sourceText, $tagPos + 1, $endPos - $tagPos - 1 );
                    $this->gotoEndPosition( $comment_text, $currentLine, $currentColumn, $endLine, $endColumn );
                    $textElements[] = array( "text" => $comment_text,
                                             "type" => EZ_ELEMENT_COMMENT,
                                             'placement' => array( 'templatefile' => $relatedTemplateName,
                                                                   'start' => array( 'line' => $currentLine,
                                                                                     'column' => $currentColumn,
                                                                                     'position' => $tagPos + 1 ),
                                                                   'stop' => array( 'line' => $endLine,
                                                                                    'column' => $endColumn,
                                                                                    'position' => $endPos - 1 ) ) );
                    if ( $sourcePosition < $blockEnd )
                        $sourcePosition = $blockEnd;
                    $currentLine = $endLine;
                    $currentColumn = $endColumn;
//                     eZDebug::writeDebug( "eZTemplate: Comment: $comment" );
                }
                else
                {
                    $tmp_pos = $tagPos;
                    while( ( $endPos = strpos( $sourceText, $rightDelimiter, $tmp_pos ) ) !== false )
                    {
                        if ( $sourceText[$endPos-1] != "\\" )
                            break;
                        $tmp_pos = $endPos + 1;
                    }
                    if ( $endPos === false )
                    {
                        // Unterminated tag
                        unset( $data );
                        $data =& substr( $sourceText, $sourcePosition );
                        $this->gotoEndPosition( $data, $currentLine, $currentColumn, $endLine, $endColumn );
                        $textBefore = substr( $sourceText, $sourcePosition, $tagPos - $sourcePosition );
                        $textPortion = substr( $sourceText, $tagPos );
                        $this->gotoEndPosition( $textBefore, $currentLine, $currentColumn, $tagStartLine, $tagStartColumn );
                        $this->gotoEndPosition( $textPortion, $tagStartLine, $tagStartColumn, $tagEndLine, $tagEndColumn );
                        $tpl->error( "", "parser error @ $relatedTemplateName:$currentLine" . "[$currentColumn]" . "\n" .
                                     "Unterminated tag, needs a $rightDelimiter to end the tag.\n" . $leftDelimiter . $textPortion,
                                     array( array( $tagStartLine, $tagStartColumn, $tagPosition ),
                                              array( $tagEndLine, $tagEndColumn, $sourceLength - 1 ),
                                              $relatedTemplateName ) );
                        $textElements[] = array( "text" => $data,
                                                 "type" => EZ_ELEMENT_TEXT,
                                                 'placement' => array( 'templatefile' => $relatedTemplateName,
                                                                       'start' => array( 'line' => $currentLine,
                                                                                         'column' => $currentColumn,
                                                                                         'position' => $sourcePosition ),
                                                                       'stop' => array( 'line' => $endLine,
                                                                                        'column' => $endColumn,
                                                                                        'position' => $sourceLength - 1 ) ) );
                        $sourcePosition = $sourceLength;
                        $currentLine = $endLine;
                        $currentColumn = $endColumn;
                    }
                    else
                    {
                        $blockEnd = $endPos + 1;
                        $len = $endPos - $tagPos;
                        if ( $sourcePosition < $blockStart )
                        {
                            // Add text before tag.
                            unset( $data );
                            $data =& substr( $sourceText, $sourcePosition, $blockStart - $sourcePosition );
                            $this->gotoEndPosition( $data, $currentLine, $currentColumn, $endLine, $endColumn );
                            $textElements[] = array( "text" => $data,
                                                     "type" => EZ_ELEMENT_TEXT,
                                                     'placement' => array( 'templatefile' => $relatedTemplateName,
                                                                           'start' => array( 'line' => $currentLine,
                                                                                             'column' => $currentColumn,
                                                                                             'position' => $sourcePosition ),
                                                                           'stop' => array( 'line' => $endLine,
                                                                                            'column' => $endColumn,
                                                                                            'position' => $blockStart ) ) );
                            $currentLine = $endLine;
                            $currentColumn = $endColumn;
                        }

                        unset( $tag );
                        $tag = substr( $sourceText, $tagPos, $len );
                        $tag = preg_replace( "/\\\\[}]/", "}", $tag );
                        $tagTrim = trim( $tag );
                        $isEndTag = false;
                        $isSingleTag = false;

                        if ( $tag[0] == "/" )
                        {
                            $isEndTag = true;
                            $tag = substr( $tag, 1 );
                        }
                        else if ( $tagTrim[strlen( $tagTrim ) - 1] == "/" )
                        {
                            $isSingleTag = true;
                            $tagTrim = trim( substr( $tagTrim, 0, strlen( $tagTrim ) - 1 ) );
                            $tag = $tagTrim;
                        }

                        $this->gotoEndPosition( $tag, $currentLine, $currentColumn, $endLine, $endColumn );
                        if ( $tag[0] == "$" or
                             $tag[0] == "\"" or
                             $tag[0] == "'" or
                             is_numeric( $tag[0] ) or
                             ( $tag[0] == '-' and
                               isset( $tag[1] ) and
                               is_numeric( $tag[1] ) ) or
                             preg_match( "/^[a-z0-9_-]+\(/", $tag ) )
                        {
                            $textElements[] = array( "text" => $tag,
                                                     "type" => EZ_ELEMENT_VARIABLE,
                                                     'placement' => array( 'templatefile' => $relatedTemplateName,
                                                                           'start' => array( 'line' => $currentLine,
                                                                                             'column' => $currentColumn,
                                                                                             'position' => $blockStart + 1 ),
                                                                           'stop' => array( 'line' => $endLine,
                                                                                            'column' => $endColumn,
                                                                                            'position' => $blockEnd - 1 ) ) );
                        }
                        else
                        {
                            $type = EZ_ELEMENT_NORMAL_TAG;
                            if ( $isEndTag )
                                $type = EZ_ELEMENT_END_TAG;
                            else if ( $isSingleTag )
                                $type = EZ_ELEMENT_SINGLE_TAG;
                            $spacepos = strpos( $tag, " " );
                            if ( $spacepos === false )
                                $name = $tag;
                            else
                                $name = substr( $tag, 0, $spacepos );
                            if ( isset( $tpl->Literals[$name] ) )
                            {
                                $literalEndTag = "{/$name}";
                                $literalEndPos = strpos( $sourceText, $literalEndTag, $blockEnd );
                                if ( $literalEndPos === false )
                                    $literalEndPos = $sourceLength;
                                $data = substr( $sourceText, $blockEnd, $literalEndPos - $blockEnd );
                                $this->gotoEndPosition( $data, $currentLine, $currentColumn, $endLine, $endColumn );
                                $blockEnd = $literalEndPos + strlen( $literalEndTag );
                                $textElements[] = array( "text" => $data,
                                                         "type" => EZ_ELEMENT_TEXT,
                                                         'placement' => false );
                            }
                            else
                                $textElements[] = array( "text" => $tag,
                                                         "name" => $name,
                                                         "type" => $type,
                                                         'placement' => array( 'templatefile' => $relatedTemplateName,
                                                                               'start' => array( 'line' => $currentLine,
                                                                                                 'column' => $currentColumn,
                                                                                                 'position' => $blockStart + 1 ),
                                                                               'stop' => array( 'line' => $endLine,
                                                                                                'column' => $endColumn,
                                                                                                'position' => $blockEnd - 1 ) ) );
                        }

                        if ( $sourcePosition < $blockEnd )
                            $sourcePosition = $blockEnd;
                        $currentLine = $endLine;
                        $currentColumn = $endColumn;
                    }
                }
            }
        }
        return $textElements;
    }

    function &parseWhitespaceRemoval( &$tpl, &$textElements )
    {
        if ( $tpl->ShowDetails )
            eZDebug::addTimingPoint( "Parse pass 2 (whitespace removal)" );
        $tempTextElements = array();
        reset( $textElements );
        while ( ( $key = key( $textElements ) ) !== null )
        {
            unset( $element );
            $element =& $textElements[$key];
            next( $textElements );
            $next_key = key( $textElements );
            unset( $next_element );
            $next_element = null;
            if ( $next_key !== null )
                $next_element =& $textElements[$next_key];
            switch ( $element["type"] )
            {
                case EZ_ELEMENT_COMMENT:
                {
                    // Ignore comments
                } break;
                case EZ_ELEMENT_TEXT:
                case EZ_ELEMENT_VARIABLE:
                {
                    if ( $next_element !== null )
                    {
                        switch ( $next_element["type"] )
                        {
                            case EZ_ELEMENT_END_TAG:
                            case EZ_ELEMENT_SINGLE_TAG:
                            case EZ_ELEMENT_NORMAL_TAG:
                            {
                                unset( $text );
                                $text =& $element["text"];
                                $text_cnt = strlen( $text );
                                if ( $text_cnt > 0 )
                                {
                                    $char = $text[$text_cnt - 1];
                                    if ( $char == "\n" )
                                    {
                                        $text = substr( $text, 0, $text_cnt - 1 );
                                    }
                                }
                            } break;
                        }
                    }
                    if ( $element["text"] !== '' )
                    {
                        $tempTextElements[] =& $element;
                    }
                } break;
                case EZ_ELEMENT_END_TAG:
                case EZ_ELEMENT_SINGLE_TAG:
                case EZ_ELEMENT_NORMAL_TAG:
                {
                    unset( $name );
                    $name =& $element["name"];
                    $startLine = false;
                    $startColumn = false;
                    $stopLine = false;
                    $stopColumn = false;
                    $templateFile = false;
                    $hasStartPlacement = false;
                    {
                        if ( $next_element !== null )
                        {
                            switch ( $next_element["type"] )
                            {
                                case EZ_ELEMENT_TEXT:
                                case EZ_ELEMENT_VARIABLE:
                                {
                                    unset( $text );
                                    $text =& $next_element["text"];
                                    $text_cnt = strlen( $text );
                                    if ( $text_cnt > 0 )
                                    {
                                        $char = $text[0];
                                        if ( $char == "\n" )
                                        {
                                            $text = substr( $text, 1 );
                                        }
                                    }
                                } break;
                            }
                        }
                        $tempTextElements[] =& $element;
                    }
                } break;
            }
        }
        return $tempTextElements;
    }

    function appendChild( &$root, &$node )
    {
        if ( !is_array( $root[1] ) )
            $root[1] = array();
        $root[1][] =& $node;
    }

    function parseIntoTree( &$tpl, &$textElements, &$treeRoot,
                            $rootNamespace, $relatedResource, $relatedTemplateName )
    {
        $currentRoot =& $treeRoot;
        if ( $tpl->ShowDetails )
            eZDebug::addTimingPoint( "Parse pass 3 (build tree)" );

        $tagStack = array();

        reset( $textElements );
        while ( ( $key = key( $textElements ) ) !== null )
        {
            unset( $element );
            $element =& $textElements[$key];
            $elementPlacement = $element['placement'];
            $startLine = $elementPlacement['start']['line'];
            $startColumn = $elementPlacement['start']['column'];
            $startPosition = $elementPlacement['start']['position'];
            $stopLine = $elementPlacement['stop']['line'];
            $stopColumn = $elementPlacement['stop']['column'];
            $stopPosition = $elementPlacement['stop']['position'];
            $templateFile = $elementPlacement['templatefile'];
            $placement = array( array( $startLine,
                                       $startColumn,
                                       $startPosition ),
                                array( $stopLine,
                                       $stopColumn,
                                       $stopPosition ),
                                $templateFile );
            switch ( $element["type"] )
            {
                case EZ_ELEMENT_TEXT:
                {
                    unset( $node );
                    $node = array( EZ_TEMPLATE_NODE_TEXT,
                                   false,
                                   $element['text'],
                                   $placement );
                    $this->appendChild( $currentRoot, $node );
                } break;
                case EZ_ELEMENT_VARIABLE:
                {
                    $text =& $element["text"];
                    $text_len = strlen( $text );
                    $var_data =& $this->ElementParser->parseVariableTag( $tpl, $relatedTemplateName, $text, 0, $var_end, $text_len, $rootNamespace );

                    unset( $node );
                    $node = array( EZ_TEMPLATE_NODE_VARIABLE,
                                   false,
                                   $var_data,
                                   $placement );
                    $this->appendChild( $currentRoot, $node );
                    if ( $var_end < $text_len )
                    {
                        $placement = $element['placement'];
                        $startLine = $placement['start']['line'];
                        $startColumn = $placement['start']['column'];
                        $subText = substr( $text, 0, $var_end );
                        $this->gotoEndPosition( $subText, $startLine, $startColumn, $currentLine, $currentColumn );
                        $tpl->error( "", "parser error @ $relatedTemplateName:$currentLine" . "[$currentColumn]" . "\n" .
                                     "Extra characters found in expression, they will be ignored.\n" .
                                     substr( $text, $var_end, $text_len - $var_end ) . "' (" . substr( $text, 0, $var_end ) . ")",
                                     $placement );
                    }
                } break;
                case EZ_ELEMENT_SINGLE_TAG:
                case EZ_ELEMENT_NORMAL_TAG:
                case EZ_ELEMENT_END_TAG:
                {
                    unset( $text );
                    unset( $type );
                    $text =& $element["text"];
                    $text_len = strlen( $text );
                    $type =& $element["type"];

                    $ident_pos = $this->ElementParser->identifierEndPosition( $tpl, $text, 0, $text_len );
                    $tag = substr( $text, 0, $ident_pos - 0 );
                    $attr_pos = $ident_pos;
                    unset( $args );

                    $args = array();

                    // special handling for some functions having complex syntax
                    if ( $type == EZ_ELEMENT_NORMAL_TAG &&
                         in_array( $tag, array( 'if', 'elseif', 'while', 'for', 'foreach', 'def', 'undef',
                                                'set', 'let', 'default', 'set-block', 'append-block', 'section' ) ) )
                    {
                        $attr_pos = $this->ElementParser->whitespaceEndPos( $tpl, $text, $attr_pos, $text_len );


                        if ( $tag == 'if' || $tag == 'elseif' )
                            $this->parseUnnamedCondition( $tag, $args, $tpl, $text, $text_len, $attr_pos, $relatedTemplateName, $startLine, $startColumn, $rootNamespace );
                        elseif ( $tag == 'while' )
                            $this->parseWhileFunction( $args, $tpl, $text, $text_len, $attr_pos, $relatedTemplateName, $startLine, $startColumn, $rootNamespace );
                        elseif ( $tag == 'for' )
                            $this->parseForFunction( $args, $tpl, $text, $text_len, $attr_pos, $relatedTemplateName, $startLine, $startColumn, $rootNamespace );
                        elseif ( $tag == 'foreach' )
                            $this->parseForeachFunction( $args, $tpl, $text, $text_len, $attr_pos, $relatedTemplateName, $startLine, $startColumn, $rootNamespace );
                        elseif ( $tag == 'def' || $tag == 'undef' )
                            $this->parseDefFunction( $tag, $args, $tpl, $text, $text_len, $attr_pos, $relatedTemplateName, $startLine, $startColumn, $rootNamespace );
                        elseif ( $tag == 'set' || $tag == 'let' || $tag == 'default' )
                            $this->parseSetFunction( $tag, $args, $tpl, $text, $text_len, $attr_pos, $relatedTemplateName, $startLine, $startColumn, $rootNamespace );
                        elseif ( $tag == 'set-block' || $tag == 'append-block' )
                            $this->parseBlockFunction( $tag, $args, $tpl, $text, $text_len, $attr_pos, $relatedTemplateName, $startLine, $startColumn, $rootNamespace );
                        elseif ( $tag == 'section' )
                            $this->parseSectionFunction( $tag, $args, $tpl, $text, $text_len, $attr_pos, $relatedTemplateName, $startLine, $startColumn, $rootNamespace );
                    }
                    elseif ( $type == EZ_ELEMENT_END_TAG && $tag == 'do' )
                    {
                        $this->parseDoFunction( $args, $tpl, $text, $text_len, $attr_pos, $relatedTemplateName, $startLine, $startColumn, $rootNamespace );
                    }

                    // other functions having simplier syntax are parsed below
                    $lastPosition = false;
                    while ( $attr_pos < $text_len )
                    {
                        if ( $lastPosition !== false and
                             $lastPosition == $attr_pos )
                        {
                            break;
                        }
                        $lastPosition = $attr_pos;
                        $attr_pos_start = $this->ElementParser->whitespaceEndPos( $tpl, $text, $attr_pos, $text_len );
                        if ( $attr_pos_start == $attr_pos and
                             $attr_pos_start < $text_len )
                        {
                            $placement = $element['placement'];
                            $startLine = $placement['start']['line'];
                            $startColumn = $placement['start']['column'];
                            $subText = substr( $text, 0, $attr_pos );
                            $this->gotoEndPosition( $subText, $startLine, $startColumn, $currentLine, $currentColumn );
                            $tpl->error( "", "parser error @ $relatedTemplateName:$currentLine" . "[$currentColumn]" . "\n" .
                                         "Extra characters found, should have been a whitespace or the end of the expression\n".
                                         "Characters: '" . substr( $text, $attr_pos ) . "'" );
                            break;
                        }
                        $attr_pos = $attr_pos_start;
                        $attr_name_pos = $this->ElementParser->identifierEndPosition( $tpl, $text, $attr_pos, $text_len );
                        $attr_name = substr( $text, $attr_pos, $attr_name_pos - $attr_pos );

                        /* Skip whitespace between here and the next one */
                        $equal_sign_pos = $this->ElementParser->whitespaceEndPos( $tpl, $text, $attr_name_pos, $text_len );
                        if ( ( $equal_sign_pos < $text_len ) && ( $text[$equal_sign_pos] == '=' ) )
                        {
                            $attr_name_pos = $equal_sign_pos;
                        }

                        if ( $attr_name_pos >= $text_len or
                             ( $text[$attr_name_pos] != '=' and
                               preg_match( "/[ \t\r\n]/", $text[$attr_name_pos] ) ) )
                        {
                            unset( $var_data );
                            $var_data = array();
                            $var_data[] = array( EZ_TEMPLATE_TYPE_NUMERIC, // type
                                                 true, // content
                                                 false // debug
                                                 );
                            $args[$attr_name] = $var_data;
                            $attr_pos = $attr_name_pos;
                            continue;
                        }
                        if ( $text[$attr_name_pos] != "=" )
                        {
                            $placement = $element['placement'];
                            $startLine = $placement['start']['line'];
                            $startColumn = $placement['start']['column'];
                            $subText = substr( $text, 0, $attr_name_pos );
                            $this->gotoEndPosition( $subText, $startLine, $startColumn, $currentLine, $currentColumn );
                            $tpl->error( "", "parser error @ $relatedTemplateName:$currentLine" . "[$currentColumn]\n".
                                         "Invalid parameter characters in function '$tag': '" .
                                          substr( $text, $attr_name_pos )  . "'" );
                            break;
                        }
                        ++$attr_name_pos;
                        unset( $var_data );
                        $var_data =& $this->ElementParser->parseVariableTag( $tpl, $relatedTemplateName, $text, $attr_name_pos, $var_end, $text_len, $rootNamespace );
                        $args[$attr_name] = $var_data;
                        $attr_pos = $var_end;
                    }

                    if ( $type == EZ_ELEMENT_END_TAG and count( $args ) > 0 )
                    {
                        if ( $tag != 'do' )
                        {
                            $placement = $element['placement'];
                            $startLine = $placement['start']['line'];
                            $startColumn = $placement['start']['column'];
                            $tpl->error( "", "parser error @ $relatedTemplateName:$startLine" . "[$startColumn]" . "\n" .
                                         "End tag \"$tag\" cannot have attributes\n$tpl->LDelim/" . $text . $tpl->RDelim,
                                         $element['placement'] );
                            $args = array();
                        }
                    }

                    if ( $type == EZ_ELEMENT_NORMAL_TAG )
                    {
                        unset( $node );
                        $node = array( EZ_TEMPLATE_NODE_FUNCTION,
                                       false,
                                       $tag,
                                       $args,
                                       $placement );
                        $this->appendChild( $currentRoot, $node );
                        $has_children = true;
                        if ( isset( $tpl->FunctionAttributes[$tag] ) )
                        {
                            if ( is_array( $tpl->FunctionAttributes[$tag] ) )
                                $tpl->loadAndRegisterFunctions( $tpl->FunctionAttributes[$tag] );
                            $has_children = $tpl->FunctionAttributes[$tag];
                        }
                        else if ( isset( $tpl->Functions[$tag] ) )
                        {
                            if ( is_array( $tpl->Functions[$tag] ) )
                                $tpl->loadAndRegisterFunctions( $tpl->Functions[$tag] );
                            $has_children = $tpl->hasChildren( $tpl->Functions[$tag], $tag );
                        }
                        if ( $has_children )
                        {
                            $tagStack[] = array( "Root" => &$currentRoot,
                                                 "Tag" => $tag );
                            unset( $currentRoot );
                            $currentRoot =& $node;
                        }
                    }
                    else if ( $type == EZ_ELEMENT_END_TAG )
                    {
                        $has_children = true;
                        if ( isset( $tpl->FunctionAttributes[$tag] ) )
                        {
                            if ( is_array( $tpl->FunctionAttributes[$tag] ) )
                                $tpl->loadAndRegisterFunctions( $tpl->FunctionAttributes[$tag] );
                            $has_children = $tpl->FunctionAttributes[$tag];
                        }
                        else if ( isset( $tpl->Functions[$tag] ) )
                        {
                            if ( is_array( $tpl->Functions[$tag] ) )
                                $tpl->loadAndRegisterFunctions( $tpl->Functions[$tag] );
                            $has_children = $tpl->hasChildren( $tpl->Functions[$tag], $tag );
                        }
                        if ( !$has_children )
                        {
                            $placement = $element['placement'];
                            $startLine = $placement['start']['line'];
                            $startColumn = $placement['start']['column'];
                            $tpl->error( "", "parser error @ $relatedTemplateName:$startLine" . "[$startColumn]" . "\n" .
                                         "End tag \"$tag\" for function which does not accept children, ignoring tag",
                                         $element['placement'] );
                        }
                        else
                        {
                            unset( $oldTag );
                            unset( $oldTagName );
                            include_once( "lib/ezutils/classes/ezphpcreator.php" );
                            $oldTag =& array_pop( $tagStack );
                            $oldTagName = $oldTag["Tag"];
                            unset( $currentRoot );
                            $currentRoot =& $oldTag["Root"];

                            if ( $oldTagName != $tag )
                            {
                                $placement = $element['placement'];
                                $startLine = $placement['start']['line'];
                                $startColumn = $placement['start']['column'];
                                $tpl->error( "", "parser error @ $relatedTemplateName:$startLine" . "[$startColumn]" . "\n" .
                                             "Unterminated tag \"$oldTagName\" does not match tag \"$tag\"",
                                             $element['placement'] );
                            }

                            // a dirty hack to pass arguments specified in {/do} to the corresponding function.
                            if ( $tag == 'do' )
                            {
                                $doOpenTag =& $currentRoot[1][count( $currentRoot[1] ) - 1];
                                $doOpenTag[3] =& $args;
                            }
                        }
                    }
                    else // EZ_ELEMENT_SINGLE_TAG
                    {
                        unset( $node );
                        $node = array( EZ_TEMPLATE_NODE_FUNCTION,
                                       false,
                                       $tag,
                                       $args,
                                       $placement );
                        $this->appendChild( $currentRoot, $node );
                    }
                    unset( $tag );

                } break;
            }
            next( $textElements );
        }
        unset( $textElements );
        if ( $tpl->ShowDetails )
            eZDebug::addTimingPoint( "Parse pass 3 done" );
    }

    /*!
     * parse 'sequence' loop parameter: "sequence <array> as <$seqVar>"
     */
    function parseSequenceParameter( $parseSequenceKeyword, $funcName, &$args, &$tpl, &$text, &$text_len, &$cur_pos,
                                     $relatedTemplateName, $startLine, $startColumn, &$rootNamespace )
    {
        if ( $parseSequenceKeyword )
        {
            // parse 'sequence' keyword
            $sequenceEndPos = $this->ElementParser->identifierEndPosition( $tpl, $text, $cur_pos, $text_len );
            $sequence = substr( $text, $cur_pos, $sequenceEndPos-$cur_pos );
            if ( $sequence != 'sequence' )
            {
                $this->showParseErrorMessage( $tpl, $text, $text_len, $cur_pos, $relatedTemplateName, $startLine, $startColumn,
                                              $funcName, "Expected keyword 'sequence' not found" );
                return false;
            }
            $cur_pos = $sequenceEndPos;

            // skip whitespaces
            $cur_pos = $this->ElementParser->whitespaceEndPos( $tpl, $text, $cur_pos, $text_len );
        }

        // parse sequence array
        $seqArray =& $this->ElementParser->parseVariableTag( $tpl, $relatedTemplateName, $text, $cur_pos, $cur_pos, $text_len, $rootNamespace );
        $args['sequence_array'] =& $seqArray;

        // skip whitespaces
        $cur_pos = $this->ElementParser->whitespaceEndPos( $tpl, $text, $cur_pos, $text_len );

        // parse 'as' keyword
        $asEndPos = $this->ElementParser->identifierEndPosition( $tpl, $text, $cur_pos, $text_len );
        $word = substr( $text, $cur_pos, $asEndPos-$cur_pos );
        if ( $word != 'as' )
        {
            $this->showParseErrorMessage( $tpl, $text, $text_len, $cur_pos, $relatedTemplateName, $startLine, $startColumn,
                              $funcName, "Expected keyword 'as' not found" );
            unset( $args['sequence_array'] );
            return false;
        }
        $cur_pos = $asEndPos;

        // skip whitespaces
        $cur_pos = $this->ElementParser->whitespaceEndPos( $tpl, $text, $cur_pos, $text_len );

        // parse sequence variable
        $seqVar =& $this->ElementParser->parseVariableTag( $tpl, $relatedTemplateName, $text, $cur_pos, $cur_pos, $text_len, $rootNamespace );
        if ( !$seqVar )
        {
            $this->showParseErrorMessage( $tpl, $text, $text_len, $cur_pos, $relatedTemplateName, $startLine, $startColumn,
                                          $funcName, "Sequence variable name cannot be empty" );
            unset( $args['sequence_array'] );
            return false;
        }
        $args['sequence_var'] = $seqVar;

        return true;
    }

    /*!
    Parse {for} function.
    Syntax:
    \code
    // for <firstValue> to <lastValue> as <$loopVar> [sequence <array> as <$var>]
    \endcode
    */
    function parseForFunction( &$args, &$tpl, &$text, &$text_len, &$cur_pos,
                               $relatedTemplateName, $startLine, $startColumn, &$rootNamespace )
    {
        $firstValStartPos = $cur_pos;

        // parse first value
        $firstVal =& $this->ElementParser->parseVariableTag( $tpl, $relatedTemplateName, $text, $firstValStartPos, $firstValEndPos, $text_len, $rootNamespace
                                                             /*, EZ_TEMPLATE_TYPE_NUMERIC_BIT | EZ_TEMPLATE_TYPE_VARIABLE_BIT*/ );
        $args['first_val'] = $firstVal;
        eZDebug::writeDebug( $firstVal, '$firstVal' );

        $toStartPos = $this->ElementParser->whitespaceEndPos( $tpl, $text, $firstValEndPos, $text_len );

        // parse 'to'
        $toEndPos = $this->ElementParser->identifierEndPosition( $tpl, $text, $toStartPos, $text_len );
        $to = substr( $text, $toStartPos, $toEndPos-$toStartPos );
        if ( $to != 'to' )
        {
            $this->showParseErrorMessage( $tpl, $text, $text_len, $cur_pos, $relatedTemplateName, $startLine, $startColumn,
                                          'for', "Expected keyword 'to' not found" );
            return;
        }


        $lastValStartPos = $this->ElementParser->whitespaceEndPos( $tpl, $text, $toEndPos, $text_len );

        // parse last value
        $lastVal =& $this->ElementParser->parseVariableTag( $tpl, $relatedTemplateName, $text, $lastValStartPos, $lastValEndPos, $text_len, $rootNamespace );
        $args['last_val'] = $lastVal;

        $asStartPos = $this->ElementParser->whitespaceEndPos( $tpl, $text, $lastValEndPos, $text_len );

        // parse 'as'
        $asEndPos = $this->ElementParser->identifierEndPosition( $tpl, $text, $asStartPos, $text_len );
        $as = substr( $text, $asStartPos, $asEndPos-$asStartPos );
        if ( $as != 'as' )
        {
            $this->showParseErrorMessage( $tpl, $text, $text_len, $cur_pos, $relatedTemplateName, $startLine, $startColumn,
                                          'for', "Expected keyword 'as' not found" );
            return;
        }

        $loopVarStartPos = $this->ElementParser->whitespaceEndPos( $tpl, $text, $asEndPos, $text_len );

        // parse loop variable
        $loopVar =& $this->ElementParser->parseVariableTag( $tpl, $relatedTemplateName, $text, $loopVarStartPos, $loopVarEndPos, $text_len, $rootNamespace );
        $args['loop_var'] = $loopVar;

        if ( $loopVarEndPos == $text_len  ) // no more parameters
            $cur_pos = $loopVarEndPos;
        else
        {
            // skip whitespaces
            $cur_pos = $this->ElementParser->whitespaceEndPos( $tpl, $text, $loopVarEndPos, $text_len );

            if ( ! $this->parseSequenceParameter( true, 'for',
                               $args, $tpl, $text, $text_len, $cur_pos, $relatedTemplateName, $startLine, $startColumn, $rootNamespace ) )
                return;
        }
    }

    /*!
    Parse {foreach} function.
    Syntax:
    \code
    {foreach <array> as [$keyVar =>] $itemVar
             [sequence <array> as $sequenceVar]
             [offset <offset>]
             [max <max>]
             [reverse]
    }
    \endcode
    */
    function parseForeachFunction( &$args, &$tpl, &$text, &$text_len, &$cur_pos,
                                   $relatedTemplateName, $startLine, $startColumn, &$rootNamespace )
    {
        // parse array
        $array =& $this->ElementParser->parseVariableTag( $tpl, $relatedTemplateName, $text, $cur_pos, $cur_pos, $text_len, $rootNamespace );
        $args['array'] =& $array;

        // skip whitespaces
        $cur_pos = $this->ElementParser->whitespaceEndPos( $tpl, $text, $cur_pos, $text_len );

        // parse 'as' keyword
        $asEndPos = $this->ElementParser->identifierEndPosition( $tpl, $text, $cur_pos, $text_len );
        $word = substr( $text, $cur_pos, $asEndPos-$cur_pos );
        if ( $word != 'as' )
        {
            $this->showParseErrorMessage( $tpl, $text, $text_len, $cur_pos, $relatedTemplateName, $startLine, $startColumn,
                                          'foreach', "Expected keyword 'as' not found" );
            return;
        }
        $cur_pos = $asEndPos;

        // skip whitespaces
        $cur_pos = $this->ElementParser->whitespaceEndPos( $tpl, $text, $cur_pos, $text_len );

        // parse variable name
        $var1 =& $this->ElementParser->parseVariableTag( $tpl, $relatedTemplateName, $text, $cur_pos, $cur_pos, $text_len, $rootNamespace );

        $nextTokenPos = $this->ElementParser->whitespaceEndPos( $tpl, $text, $cur_pos, $text_len );

        // parse itemVar (if specified)
        if ( $nextTokenPos <= ( $text_len-2 ) && $text[$nextTokenPos] == '=' && $text[$nextTokenPos+1] == '>' )
        {
            // skip whitespaces
            $cur_pos = $this->ElementParser->whitespaceEndPos( $tpl, $text, $nextTokenPos+2, $text_len );

            // parse item variable name
            $itemVar =& $this->ElementParser->parseVariableTag( $tpl, $relatedTemplateName, $text, $cur_pos, $cur_pos, $text_len, $rootNamespace );

            $args['key_var']  =& $var1;
            $args['item_var'] =& $itemVar;
        }
        else
            $args['item_var'] =& $var1;

        /*
         * parse optional parameters
         */

        while ( $cur_pos < $text_len )
        {
            // skip whitespaces
            $cur_pos = $this->ElementParser->whitespaceEndPos( $tpl, $text, $cur_pos, $text_len );

            $paramNameEndPos = $this->ElementParser->identifierEndPosition( $tpl, $text, $cur_pos, $text_len );
            $paramName = substr( $text, $cur_pos, $paramNameEndPos-$cur_pos );
            $cur_pos = $paramNameEndPos;

            if ( $paramName == 'sequence' )
            {
                if ( ! $this->parseSequenceParameter( false, 'foreach',
                                               $args, $tpl, $text, $text_len, $cur_pos, $relatedTemplateName, $startLine, $startColumn, $rootNamespace ) )
                    return;
            }
            elseif ( $paramName == 'offset' )
            {
                $cur_pos = $this->ElementParser->whitespaceEndPos( $tpl, $text, $cur_pos, $text_len );
                $offset =& $this->ElementParser->parseVariableTag( $tpl, $relatedTemplateName, $text, $cur_pos, $cur_pos, $text_len, $rootNamespace );
                $args['offset'] =& $offset;
            }
            elseif ( $paramName == 'max' )
            {
                $cur_pos = $this->ElementParser->whitespaceEndPos( $tpl, $text, $cur_pos, $text_len );
                $max =& $this->ElementParser->parseVariableTag( $tpl, $relatedTemplateName, $text, $cur_pos, $cur_pos, $text_len, $rootNamespace );
                $args['max'] =& $max;
            }
            elseif ( $paramName == 'reverse' )
            {
                $reverseValText = '1';
                $args['reverse'] =& $this->ElementParser->parseVariableTag( $tpl, $relatedTemplateName, $reverseValText, 0, $reverseValPos, 1, $rootNamespace );
            }
            else
            {
                $this->showParseErrorMessage( $tpl, $text, $text_len, $cur_pos, $relatedTemplateName, $startLine, $startColumn,
                                              'foreach', "Unknown parameter '$paramName'" );
                return;
            }
        }
    }

    /*!
    Parse do..while function
    Syntax:
    \code

    {do}
        [{delimiter}...{/delimiter}]
        [{break}]
        [{continue}]
        [{skip}]
    {/do while <condition> [sequence <array> as $seqVar]}
    */
    function parseDoFunction( &$args, &$tpl, &$text, &$text_len, &$cur_pos,
                              $relatedTemplateName, $startLine, $startColumn, &$rootNamespace )
    {
        // skip whitespaces
        $cur_pos = $this->ElementParser->whitespaceEndPos( $tpl, $text, $cur_pos, $text_len );

        // parse while keyword
        $wordEndPos = $this->ElementParser->identifierEndPosition( $tpl, $text, $cur_pos, $text_len );
        $word = substr( $text, $cur_pos, $wordEndPos-$cur_pos );
        if ( $word != 'while' )
        {
            $this->showParseErrorMessage( $tpl, $text, $text_len, $cur_pos, $relatedTemplateName, $startLine, $startColumn,
                                          'do', "Expected keyword 'while' not found in parameters" );
            return;
        }
        $cur_pos = $wordEndPos;

        // skip whitespaces
        $cur_pos = $this->ElementParser->whitespaceEndPos( $tpl, $text, $cur_pos, $text_len );

        $cond =& $this->ElementParser->parseVariableTag( $tpl, $relatedTemplateName, $text, $cur_pos, $cur_pos, $text_len, $rootNamespace );
        //eZDebug::writeDebug( $cond, 'do condition' );
        $args['condition'] =& $cond;

        // skip whitespaces
        $cur_pos = $this->ElementParser->whitespaceEndPos( $tpl, $text, $cur_pos, $text_len );

        if ( $cur_pos == $text_len ) // no more arguments
            return;

        $this->parseSequenceParameter( true, 'do',
                                       $args, $tpl, $text, $text_len, $cur_pos,
                                       $relatedTemplateName, $startLine, $startColumn, $rootNamespace );
    }


    /*!
    Parse def/undef functions
    Syntax:
    \code
        {def $var1=<value1> [$var2=<value2> ...]}
        {undef [$var1 [$var2] ...]}
    \endcode
    */

    function parseDefFunction( $funcName, &$args, &$tpl, &$text, &$text_len, &$cur_pos,
                               $relatedTemplateName, $startLine, $startColumn, &$rootNamespace )
    {
        if ( $cur_pos == $text_len && $funcName == 'def' ) // no more arguments
        {
            $this->showParseErrorMessage( $tpl, $text, $text_len, $cur_pos, $relatedTemplateName, $startLine, $startColumn,
                                          $funcName, 'Not enough arguments' );
            return;
        }

        while ( $cur_pos < $text_len )
        {
            // parse variable name
            if ( $text[$cur_pos] != '$' )
            {
                $this->showParseErrorMessage( $tpl, $text, $text_len, $cur_pos, $relatedTemplateName, $startLine, $startColumn,
                                              $funcName, '($) expected' );
                return;
            }

            $cur_pos++;
            $wordEndPos = $this->ElementParser->identifierEndPosition( $tpl, $text, $cur_pos, $text_len );
            $varName = substr( $text, $cur_pos, $wordEndPos-$cur_pos );
            $cur_pos = $wordEndPos;

            if ( !$varName )
            {
                $this->showParseErrorMessage( $tpl, $text, $text_len, $cur_pos, $relatedTemplateName, $startLine, $startColumn,
                                              $funcName, 'Empty variable name' );
                return;
            }

            if ( $funcName == 'def' )
            {
                // skip whitespaces
                $cur_pos = $this->ElementParser->whitespaceEndPos( $tpl, $text, $cur_pos, $text_len );

                // parse variable value
                if ( $cur_pos >= $text_len || $text[$cur_pos] != '=' )
                {
                    $this->showParseErrorMessage( $tpl, $text, $text_len, $cur_pos, $relatedTemplateName, $startLine, $startColumn,
                                                  $funcName, '(=) expected' );
                    return;
                }
                $cur_pos++;

                // skip whitespaces
                $cur_pos = $this->ElementParser->whitespaceEndPos( $tpl, $text, $cur_pos, $text_len );

                $varValue =& $this->ElementParser->parseVariableTag( $tpl, $relatedTemplateName, $text, $cur_pos, $cur_pos, $text_len, $rootNamespace );
                $args[$varName] =& $varValue;
            }
            else
            {
                $varValueText = '1';
                $args[$varName] =& $this->ElementParser->parseVariableTag( $tpl, $relatedTemplateName, $varValueText, 0, $varValPos, 1, $rootNamespace );
            }


            // skip whitespaces
            $cur_pos = $this->ElementParser->whitespaceEndPos( $tpl, $text, $cur_pos, $text_len );
        }
    }

    /*!
    Parse arguments for {if}/{elseif}
    */
    function parseUnnamedCondition( $funcName, &$args, &$tpl, &$text, &$text_len, &$cur_pos,
                                    $relatedTemplateName, $startLine, $startColumn, &$rootNamespace )
    {
        $cond =& $this->ElementParser->parseVariableTag( $tpl, $relatedTemplateName, $text, $cur_pos, $cur_pos, $text_len, $rootNamespace );
        if ( !count( $cond ) )
        {
            $this->showParseErrorMessage( $tpl, $text, $text_len, $cur_pos, $relatedTemplateName, $startLine, $startColumn,
                                          $funcName, 'Not enough arguments' );
            return;

        }
        $args['condition'] = $cond;
    }

    /*!
    Parse arguments for {while}
    */
    function parseWhileFunction( &$args, &$tpl, &$text, &$text_len, &$cur_pos,
                                 $relatedTemplateName, $startLine, $startColumn, &$rootNamespace )
    {
        $cond =& $this->ElementParser->parseVariableTag( $tpl, $relatedTemplateName, $text, $cur_pos, $cur_pos, $text_len, $rootNamespace );
        if ( !count( $cond ) )
        {
            $this->showParseErrorMessage( $tpl, $text, $text_len, $cur_pos, $relatedTemplateName, $startLine, $startColumn,
                                          'while', 'Not enough arguments' );
            return;

        }
        $args['condition'] = $cond;

        // skip whitespaces
        $cur_pos = $this->ElementParser->whitespaceEndPos( $tpl, $text, $cur_pos, $text_len );

        if ( $cur_pos == $text_len ) // no more arguments
            return;

        $this->parseSequenceParameter( true, 'while',
                                       $args, $tpl, $text, $text_len, $cur_pos,
                                       $relatedTemplateName, $startLine, $startColumn, $rootNamespace );
    }

    /*!
    Parse arguments for {set}/{let}/{default}
    */
    function parseSetFunction( $funcName, &$args, &$tpl, &$text, &$text_len, &$cur_pos,
                               $relatedTemplateName, $startLine, $startColumn, &$rootNamespace )
    {
        while ( $cur_pos < $text_len )
        {
            $dollarSignFound = false;

            // skip optional dollar sign
            if ( $text[$cur_pos] == '$' )
            {
                $dollarSignFound = true;
                $cur_pos++;
            }

            // parse variable name
            $wordEndPos = $this->ElementParser->identifierEndPosition( $tpl, $text, $cur_pos, $text_len );
            $varName = substr( $text, $cur_pos, $wordEndPos-$cur_pos );
            $cur_pos = $wordEndPos;

            if ( !$varName )
            {
                $this->showParseErrorMessage( $tpl, $text, $text_len, $cur_pos, $relatedTemplateName, $startLine, $startColumn,
                                              $funcName, 'Empty variable name' );
                return;
            }

            /*
             * If parameter 'name' or 'scope' was passed without dollar sign
             * change its name to '-name' or '-scope', respectively.
             * In this case it is treated specially in the {set}/{let}/{default} functions.
             */
            if ( !$dollarSignFound && ( $varName == 'name' || $varName == 'scope' ) )
                $varName = "-$varName";

            // skip whitespaces
            $cur_pos = $this->ElementParser->whitespaceEndPos( $tpl, $text, $cur_pos, $text_len );

            // if no value, assume boolean true
            if ( $cur_pos >= $text_len or
                 ( $text[$cur_pos] != '=' /*and preg_match( "/[ \t\r\n]/", $text[$cur_pos] )*/ ) )
            {
                unset( $var_data );
                $var_data = array();
                $args[$varName] = array( array( EZ_TEMPLATE_TYPE_NUMERIC, // type
                                                true, // content
                                                false // debug
                                                ) );
                continue;
            }

            if ( $cur_pos >= $text_len )
                break;

            $cur_pos++;

            // skip whitespaces
            $cur_pos = $this->ElementParser->whitespaceEndPos( $tpl, $text, $cur_pos, $text_len );

            $varValue =& $this->ElementParser->parseVariableTag( $tpl, $relatedTemplateName, $text, $cur_pos, $cur_pos, $text_len, $rootNamespace );
            $args[$varName] =& $varValue;

            // skip whitespaces
            $cur_pos = $this->ElementParser->whitespaceEndPos( $tpl, $text, $cur_pos, $text_len );
        }
    }

    /*!
    Parse arguments for {set-block}/{append-block}.
    This method has been created to correctly handle the case when ($) is used in variable name, e.g. {set-block variable=$var}
    Here we strip the dollar sign and pass the variable name as string.
    */
    function parseBlockFunction( $funcName, &$args, &$tpl, &$text, &$text_len, &$cur_pos,
                                 $relatedTemplateName, $startLine, $startColumn, &$rootNamespace )
    {
        while ( $cur_pos < $text_len )
        {
            // parse parameter name
            $wordEndPos = $this->ElementParser->identifierEndPosition( $tpl, $text, $cur_pos, $text_len );
            $paramName  = substr( $text, $cur_pos, $wordEndPos-$cur_pos );
            $cur_pos    = $wordEndPos;
            if ( !$paramName )
            {
                $this->showParseErrorMessage( $tpl, $text, $text_len, $cur_pos, $relatedTemplateName, $startLine, $startColumn,
                                              $funcName, 'Empty parameter name' );
                return;
            }

            // skip whitespaces
            $cur_pos = $this->ElementParser->whitespaceEndPos( $tpl, $text, $cur_pos, $text_len );

            // skip (=)
            if ( $text[$cur_pos] != '=' )
            {
                $this->showParseErrorMessage( $tpl, $text, $text_len, $cur_pos, $relatedTemplateName, $startLine, $startColumn,
                                              $funcName, '(=) expected' );
                return;
            }
            $cur_pos++;

            // skip whitespaces
            $cur_pos = $this->ElementParser->whitespaceEndPos( $tpl, $text, $cur_pos, $text_len );

            // skip optional dollar sign
            if ( $paramName == 'variable' && $cur_pos < $text_len && $text[$cur_pos] == '$' )
            {
                $cur_pos++;
            }

            // parse parameter value
            $paramValue =& $this->ElementParser->parseVariableTag( $tpl, $relatedTemplateName, $text, $cur_pos, $cur_pos, $text_len, $rootNamespace );
            $args[$paramName] =& $paramValue;

            // skip whitespaces
            $cur_pos = $this->ElementParser->whitespaceEndPos( $tpl, $text, $cur_pos, $text_len );
        }
    }

    /*!
    Parse arguments for {section}.
    This method has been created to correctly handle the case when ($) is used in variable name, e.g. {section var=$item}
    Here we strip the dollar sign and pass the variable name as string.
    */
    function parseSectionFunction( $funcName, &$args, &$tpl, &$text, &$text_len, &$cur_pos,
                                   $relatedTemplateName, $startLine, $startColumn, &$rootNamespace )
    {
        while ( $cur_pos < $text_len )
        {
            // parse parameter name
            $wordEndPos = $this->ElementParser->identifierEndPosition( $tpl, $text, $cur_pos, $text_len );
            $paramName  = substr( $text, $cur_pos, $wordEndPos-$cur_pos );
            $cur_pos    = $wordEndPos;
            if ( !$paramName )
            {
                $this->showParseErrorMessage( $tpl, $text, $text_len, $cur_pos, $relatedTemplateName, $startLine, $startColumn,
                                              $funcName, 'Empty parameter name' );
                return;
            }

            // skip whitespaces
            $cur_pos = $this->ElementParser->whitespaceEndPos( $tpl, $text, $cur_pos, $text_len );

            // skip (=)
            if ( $cur_pos >= $text_len || $text[$cur_pos] != '=' ) // if the parameter has no value, i.e. not followed by '=<value>'
            {
                // the parameter gets boolean true value.
                $args[$paramName] = array( array( EZ_TEMPLATE_TYPE_NUMERIC, // type
                                                  true, // content
                                                  false // debug
                                                  ) );
                continue;
            }
            $cur_pos++;

            // skip whitespaces
            $cur_pos = $this->ElementParser->whitespaceEndPos( $tpl, $text, $cur_pos, $text_len );

            // skip optional dollar sign that is allowed in value of 'var' parameter
            if ( $paramName == 'var' && $cur_pos < $text_len && $text[$cur_pos] == '$' )
            {
                $cur_pos++;
            }

            // parse parameter value
            $paramValue =& $this->ElementParser->parseVariableTag( $tpl, $relatedTemplateName, $text, $cur_pos, $cur_pos, $text_len, $rootNamespace );
            $args[$paramName] =& $paramValue;

            // skip whitespaces
            $cur_pos = $this->ElementParser->whitespaceEndPos( $tpl, $text, $cur_pos, $text_len );
        }
    }

    function showParseErrorMessage( &$tpl, &$text, $text_len, &$cur_pos, $tplName, $startLine, $startColumn, $funcName, $message )
    {
        $subText = substr( $text, 0, $cur_pos );
        $this->gotoEndPosition( $subText, $startLine, $startColumn, $currentLine, $currentColumn );
        $tpl->error( $funcName, "parser error @ $tplName:$currentLine\n" .
                     "$message at [" . substr( $text, $cur_pos ) . "]" );
        $cur_pos = $text_len;
    }

    function &instance()
    {
        $instance =& $GLOBALS['eZTemplateMultiPassParserInstance'];
        if ( get_class( $instance ) != 'eztemplatemultipassparser' )
        {
            $instance = new eZTemplateMultiPassParser();
        }
        return $instance;
    }

    /// \privatesection
    var $ElementParser;
}

?>
