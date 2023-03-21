# EDITOR FEATURES
SD bee's collaborative editor has the following features :

## JSON storage

Documents are stored in JSON format as a pile of elements ordered by their name.

## Elements

Elements include simple text elements like titles and paragraphs, containers (views and zones), tables, lists and complex objects such as connectors.
There are also elements for including HTML, styles and programs.

## Element compilation

Every document has a model, and elements from multiple levels of models are compiled before rendering.

## Views

Every element of a document is contained in a view. Only one view is displayed at a time. Views are used to seperate stages in a process, languages, organisation and layouts.

## Contextual menu

The editor does not rely on extended menus. Instead a short contextual menu is provided on the element being edited. This menu uses some highly generic functions defined here.

### Styling

For styling, a small choice of style classes is provided based on what the model's document has defined.

### Layouts

Layouts also use style classes

### Cloud

Import content from the web, either by searching directly or via a connector.

### Ideas

Use a suggeston provided by the system

### Config

This option only appears when editing a model. You cans et wether the element will be editable to the user of the model.

### Code

This option is for editing HTML content


## Formulas

Formulas can be placed in table cells and as fields in any other element.


## Naming

All elements can be named and these names are used in formulas and programs


## View types

Views have a type used to determine which elements can be inserted there, helping for example to group style and programs in dedidcated views


## Resource library

Program code and styling may draw from an extensible resource library using a variety of style and programming format

## Services

Progam code accesses third-party applications via an extensible service gateway with in-built throttling (for invoiced services) and secure credentals management.


## Clipboard

The clipboard is replaced by a permanent gallery of clips that can be initialised by the model

## Task management and automation

Documents contain task management information and automation data for executing functions without user intervention



<ul>
  <li>easy-to-parse JSON storage of element stack and task progress</li>
  <li>50+ built-in element types extendable with personalised ones    
  </li>
  <li>element compilation from multiple levels of models</li>
  <li>multiple views (display sets) for task steps, languages, organisation and layouts</li>
  <li>a single and short contextual menu with generic functions, controled by the model, for choosing style classes, importing content and getting suggestions</li> 
  <li>formulas can be placed in table cells and any other element</li>
  <li>all elements can be named and these names are used in formulas and programs</li>
  <li>views have a type used to determine which elements can be inserted there, helping for example to group style and programs in dedidcated views</li>
  <li>program code and styling may draw from an extensible resource library using a variety of style and programming formats</li>
  <li>progam code accesses third-party applications via an extensible service gateway with in-built throttling (for invoiced services) and secure credentals management</li>
  <li>the clipboard is replaced by a permanent gallery of clips that can be initialised by the model</li>
  <li>documents contain task management information</li>
  <li>PLANNED : documents contain automation data for executing functions without user intervention</li>
</ul>