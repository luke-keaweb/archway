<?php

class SimpleForm {

  var $f;
  var $name;
  var $value;
  var $class;
  var $placeholder;

  function __construct() {
    $this->f = array();
    return $this->f;
  }

  function text() {
    $this->f = array();
    $this->f['type'] = 'text';
    return $this;
  }

  function search() {
    $this->f = array();
    $this->f['type'] = 'search';
    return $this;
  }

  function hidden() {
    $this->f = array();
    $this->f['type'] = 'hidden';
    return $this;
  }

  function date() {
    $this->f = array();
    $this->f['type'] = 'date';
    return $this;
  }

  function number() {
    $this->f = array();
    $this->f['type'] = 'number';
    return $this;
  }
  
  function checkbox() {
    $this->f = array();
    $this->f['type'] = 'checkbox';
    return $this;
  }
  
  function radio() {
    $this->f = array();
    $this->f['type'] = 'radio';
    return $this;
  }
  
  function textarea() {
    $this->f = array();
    $this->f['type'] = 'textarea';
    return $this;
  }

  function select() {
    $this->f = array();
    $this->f['type'] = 'select';
    return $this;
  }

  function min( $min ) {
    $this->f['min'] = $min;
    return $this;
  }

  function max( $max ) {
    $this->f['max'] = $max;
    return $this;
  }

  function label($label) {
    $this->f['label'] = $label;
    return $this;
  }

  function tooltip($text) {
    $this->f['tooltip'] = $text;
    return $this;
  }
  
  function radioNewlines() {
    $this->f['radio_newlines'] = true;
    return $this;
  }

  function name($name) {
    $this->f['name'] = $name;
    return $this;
  }

  function value($value) {
    $this->f['value'] = $value;
    return $this;
  }
  
  function default($default) {
    $this->f['default'] = $default;
    return $this;
  }

  function class($class) {
    $this->f['class'] = $class;
    return $this;
  }

  function placeholder($placeholder) {
    $this->f['placeholder'] = $placeholder;
    return $this;
  }

  function autofocus() {
    $this->f['autofocus'] = true;
    return $this;
  }

  function required() {
    $this->f['required'] = true;
    return $this;
  }


  function options(array $options) {
    $this->f['options'] = $options;
    return $this;
  }


  function render() {
    echo $this->getHTML();
  } // end of render


  function getHTML() {

    $html = '';

    // assemble the label
    if ( isset($this->f['label']) ) {
      $label_tooltip = $this->f['tooltip'] ?? false;
      
      $label_class = false;
      if ($this->f['type'] == 'checkbox')
        $label_class = 'checkbox';
        
      // textarea labels inherit the class, because I say so 
      if ($this->f['type'] == 'textarea' && isset($this->f['class']) )
        $label_class = $this->f['class'];
      
      $html .= '<label class="'.$label_class.'" title="'.$label_tooltip.'" for="form_'.$this->f['name'].'">';
        $html .= $this->f['label'];
      $html .= '</label>'.PHP_EOL;
    }

    if ($this->f['type'] == 'textarea')
      $html .= $this->makeTextarea();
    elseif ($this->f['type'] == 'radio')
      $html .= $this->makeRadio();
    elseif ($this->f['type'] == 'select')
      $html .= $this->makeSelect();
    else
      $html .= $this->makeInput();

    // should we wrap the label + input in a div?
    if ( isset($this->f['wrap']) && $this->f['wrap'] == true)
      return $this->wrapInDiv($html);

    return $html;

  } // end of getHTML

  function wrap() {
    // whether or not to wrap the label + input in a div
    $this->f['wrap'] = true;
    return $this;
  }

  function wrapInDiv( $html ) {
    return '<div class="form-section">'.$html.'</div>'.PHP_EOL;
  }

  function makeInput() {

    $html = '';

    $html .= '<input id="form_'.$this->f['name'].'" type="'.$this->f['type'].'"';
    $html .= ' name="'.$this->f['name'].'"';

    // if we are specifically handed a value, put one in
    // otherwise, auto-fill from GET / POST
    if ( isset($this->f['value']) )
      $html .= ' value="'.cleanString( $this->f['value'] ).'"';
    else
      $html .= ' value="'.$this->autoFillValue().'"';

    $html .= ' spellcheck="false"';

    if ( isset($this->f['class']) )
      $html .= ' class="'.$this->f['class'].'"';

    if ( isset($this->f['min']) )
      $html .= ' min="'.$this->f['min'].'"';

    if ( isset($this->f['max']) )
      $html .= ' max="'.$this->f['max'].'"';

    if ( isset($this->f['placeholder']) )
      $html .= ' placeholder="'.$this->f['placeholder'].'"';

    if ( isset($this->f['autofocus']) )
      $html .= ' autofocus';

    if ( isset($this->f['required']) )
      $html .= ' required';
      
          
    // for checkboxes, decide if they are ticked  
    if ( 
      $this->f['type'] == 'checkbox' 
      && isset($this->f['value'])
      && $this->isValueSet( $this->f['value'] ) 
    )
      $html .= " checked";


    $html .= ' />';

    return $html;

  } // end of makeInput



  function makeRadio() {
    
    if (
      !isset($this->f['options']) 
      || !is_array($this->f['options'])
    )
      throw new Exception('Tried to generate radio buttons without any options!');
    
    $html = '';
  
    $counter = 0;
  
    foreach ($this->f['options'] as $label => $value) {
      
      if ( isset($this->f['radio_newlines']) && $counter != 0 )
        $html .= '<label></label> ';   // a blank label, what a hack
      
      $id = $this->f['name'].'_'.$value;  // Create a unique ID
      $html .= '<input type="radio" id="'.$id.'" name="'.$this->f['name'].'" value="'.$value.'"';

      if (isset($this->f['class']))
        $html .= ' class="'.$this->f['class'].'"';

      if ($this->isValueSet($value))
        $html .= ' checked';

      $html .= '>';
      $html .= '<label class="radio" for="'.$id.'">'.$label.'</label>';  // Label with 'for' attribute
      
      if ( isset($this->f['radio_newlines']) )
        $html .= '<br />';

      $counter++;

    } // end of foreach

    return $html;
  }



  function makeTextarea() {
    $html = '<textarea id="form_' . $this->f['name'] . '" name="' . $this->f['name'] . '"';
    
    if (isset($this->f['class'])) {
      $html .= ' class="' . $this->f['class'] . '"';
    }

    if (isset($this->f['placeholder'])) {
      $html .= ' placeholder="' . $this->f['placeholder'] . '"';
    }
    
    if (isset($this->f['required'])) {
      $html .= ' required';
    }

    $html .= '>';
    
    if (isset($this->f['value'])) {
      $html .= htmlspecialchars($this->f['value']);
    }
    
    $html .= '</textarea>';
    
    return $html;
  }


	function makeSelect() {

		// sanity check
    // we need to make sure we have an associative array
    // ...

    $select_tooltip = $this->f['tooltip'] ?? false;

		// start outputting the select input
		$html = '<select id="form_'.$this->f['name'].'" title="'.$select_tooltip.'" name="'.$this->f['name'].'"';

    if ( isset($this->f['required']) )
      $html .= ' required';

    if ( isset($this->f['class']) )
      $html .= ' class="'.$this->f['class'].'"';  

		$html .= '>';

		foreach ($this->f['options'] as $label => $value) {

			$html .= '<option value="'.$value.'"';

			// should this option be selected?
			if ( $this->isValueSet($value) )
        $html .= " selected";

			$html .= '>'.$label.'</option>';

		} // end of options foreach

		$html .= '</select>';

    return $html;

	} // end of select


  function isValueSet( $value ) {

    // for select dropdowns and checkboxes, we need to check if the provided $value should be selected/checked

    if (
      ( isset($_POST[ $this->f['name'] ])
      && $_POST[ $this->f['name'] ] == $value )
    ||
      ( isset($_GET[ $this->f['name'] ])
      && $_GET[ $this->f['name'] ] == $value )
    )
      return true;

    // ok, if the field is NOT already set by POST or GET, do we have a default value?
    if (
      !isset($_POST[ $this->f['name'] ])
      && !isset($_GET[ $this->f['name'] ])
      && isset($this->f['default'])
      && $this->f['default'] == $value
    )
      return true;

    // otherwise, no
    return false;

  } // end of isValueSet


  function autoFillValue() {

    // sanity check
    if (!$this->f['name'])
      throw new Exception('Un-named form field!');
    
    // have we got this form's value as a POST variable?
    if ( isset($_POST[ $this->f['name'] ]) )
      return cleanString( $_POST[ $this->f['name'] ] );
    
    // have we got this form's value as a GET variable?
    if ( isset($_GET[ $this->f['name'] ]) )
      return cleanString( $_GET[ $this->f['name'] ] );
    
    // otherwise, no value
    return false;  
    
  } // end of autoFillValue
  




  function start(
    $method="GET",
    $action=null,
    $target=null,
    $class=null
  ) {
    
    if (!$action)
      $action = Router::getPrettyUrl();
    
    // todo: id for form
    return '<form method="'.$method.'" action="'.$action.'" target="'.$target.'" class="'.$class.'" hx-boost="true" hx-indicator="body">';
    // 

  } // end of start form

  function end() {
    return '</form>';
  }

  function button(
    $text,
    $class=null,
    $title=null
  ) {

    return '<button value="Search" class="'.$class.'" title="'.$title.'">'.$text.'</button>';

  } // end of button





} // end of class
