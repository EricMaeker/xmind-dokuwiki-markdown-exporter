<?php

// TEST
// php xmindtomd.php -f "../../Documents/Maps/Temps et Empathie - JASFFG41.xmind" -d -l3 -o"/Users/eric/Sites/farm/books/data/pages/fr/medical/in_progress/jasfgg41_2.txt"

/**
 * PHP command line script
 * XMind file exporter to Dokuwiki and Markdown syntax
 * 
 * Run command line to get help about this script
 * php xmindtomd -v
 *
 * Licence: BSD-3-Clauses
 * Author: Eric Maeker, MD, France, 2021-2022.
 */
class XMindToMD {
    private $logDebug = false;
    private $debugStart = "*** Debug".PHP_EOL;
    private $debugEnd = "*** /Debug".PHP_EOL;
    private $contentFile = "content.json";
    private $options = NULL;
    private $version = 1640762564; // echo $(date +%s)  

    // Output raw
    private $lastH1 = "";
    private $lastH2 = "";
    private $output = "";

    // Reveal JS var
    private $rjs = Array(  
      "commands" => Array(
         "titre",
         "titrecourt",
         "titrelong",
         "auteurs",
         "affiliation",
         "date",
         "footer",
         "style",
         "bandeau",
         "extrastyle",
         "bg-anim",
         "slide-transition",
         ),

      // Computed slides
      "output" => "",
      "currentSlide" => "",
      "currentSlideEmpty" => true,
      "map" => "",                 // map of presentation
      "mapTag" => "%%revealjs_map_tag%%",  // Where map will be inserted at the end of the process

      // File start and end blocks
      "outputStart" => "~~REVEAL~~\n~~NOCACHE~~\n\n",
      "outputEnd" => "",

      // Slides start and end blocks
      "slideStart" => "\n",
      "slideEnd" => "\n",

      // Footer
      "footerFormat" => "<wrap footer>%s</wrap>\n\n",
    );

    // Style
    private $beforeH1 = "";
    private $afterH1 = "";
    private $beforeH2 = "";
    private $afterH2 = "";
    private $beforeH3 = "";
    private $afterH3 = "";
    private $beforeH4 = "";
    private $afterH4 = "";
    private $nheaders = 2; // Number of header levels 
    private $outputExt = ".md";
    private $style = "";
    private $styleLf = "";
    private $preBold = "";
    private $postBold = "";
    private $preItalic = "";
    private $postItalic = "";

    /**
     * Initialization of the object
     * Read command line and set style
     */
    public function __construct() {
        $this->readCLIOptions();
    }
    
    /**
     * Read command line options
     * Run command line to get help about this script
     * php xmindtomd -v
     */
    private function readCLIOptions() {
        // Script example.php
        $shortopts  = "";
        $shortopts .= "f:";  // input XMind file
        $shortopts .= "s:";  // style
        $shortopts .= "o::"; // output (optional)
        $shortopts .= "l::"; // output (optional)
        $shortopts .= "hvdm";  // help, version, dokuwiki, markdown
 
        $longopts  = array(
//           "file:",
//           "output::",
//           "help",
//           "version",
//           "dokuwiki",
//           "markdown",
        );
        $this->options = getopt($shortopts, $longopts);
        
        if (array_key_exists("h", $this->options)) {
          global $argv;
          echo PHP_EOL;
          echo "This command line script exports XMind files to Dokuwiki or Markdown.".PHP_EOL;
          echo "Example: php ".$argv[0]." -f xmindfile.xmind -d -o dokuwikified.txt".PHP_EOL;
          echo "Options ".PHP_EOL;
          echo "    -h   get this help".PHP_EOL;
          echo "    -v   get script version".PHP_EOL;
          echo "    -d   Export to Dokuwiki syntax".PHP_EOL;
          echo "    -m   Export to Markdown syntax".PHP_EOL;
          echo "    -l   Level of headers".PHP_EOL;
          echo PHP_EOL;
          echo "Written by Eric Maeker, MD, France.".PHP_EOL;
          echo "See: https://github.com/EricMaeker".PHP_EOL;
          echo PHP_EOL;
          exit(0);
        }
        if (array_key_exists("v", $this->options)) {
          global $argv;
          echo PHP_EOL;
          echo "PHP Script: ".$argv[0]." - Version: ".$this->version.PHP_EOL;
          exit(0);
        }
        
        // Set style
        // Default style is Markdown
        $this->setStyle("md");
        if (array_key_exists("d", $this->options)) {
          $this->setStyle("doku");
        } else if (array_key_exists("m", $this->options)) {
          $this->setStyle("md");
        }
        
        // Check header levels
        if (array_key_exists("l", $this->options) &&
            !empty($this->options["l"])) {
          $this->nheaders = $this->options["l"];
        }        
    }

    /**
     * Start exportation of the XMind file
     */
    public function runExport(){
        $fn = $this->options["f"];
        echo "* Reading file: ".$fn.PHP_EOL;

        // Test file
        // Exists?
        if (!file_exists($fn)) {
            echo "Error: ".$fn." does not exists.".PHP_EOL;
            exit(1);
        }
        // XMind extension?
        if (substr($fn, -6) !== ".xmind") {
            echo "Error: ".$fn." wrong extension.".PHP_EOL;
            exit(2);
        }

        // Unzip file
        $contents = file_get_contents("zip://".$fn."#".$this->contentFile);

        if (empty($contents)) {
            echo "Error: ".$fn." extracted content NULL.".PHP_EOL;
            exit(3);
        }
        
        // Read JSON
        echo "* Decoding JSON content".PHP_EOL;
        $json = json_decode($contents, false);

        // Log debug
        if ($this->logDebug) {
            echo $this->debugStart;
            echo "File to export: ".$file.PHP_EOL;
            echo print_r($json);
            echo $this->debugEnd;
        }
        
        // Get root topic
        echo "* Exporting style: ".$this->style.PHP_EOL;
        echo "* Number of header level: ".$this->nheaders.PHP_EOL;
        $node = $json[0]->rootTopic;        
        $this->readNode($node);
    }

    /**
     * Read one JSON node of the XMind file. Read the node and all its children.
     * Outputs rendered text in var $this->output
     * 
     * $node is the current to read
     * $level is the current level of indentation
     *
     * \note Recursive function
     */
    private function readNode($node, $level = 0) {
      if ($this->_isRevealNode($node)) {
        $this->_readRevealNode($node, $level);
        return true;
      }
      $withoutLf = str_replace("\n", " / ", $node->title);
      // Convert LF to dokuwiki/markdown style
      $withLf = str_replace("\n", $this->styleLf, $node->title);
      $indent = $this->_getLineIndent($level, $this->nheaders);
      switch ($level) {
        case 0:
          if ($this->nheaders > 0) {
            $this->output .= PHP_EOL.PHP_EOL;
            $this->output .= $this->beforeH1;
            $this->output .= $withoutLf;
            $this->output .= $this->afterH1;
            $this->output .= PHP_EOL.PHP_EOL;
            $this->lastH1 = $withoutLf;
            $this->lastH2 = "";
            $this->lastH3 = "";
            break;
          }
        case 1:
          if ($this->nheaders > 1) {
            $this->output .= PHP_EOL.PHP_EOL;
            $this->output .= $this->beforeH2;
            $this->output .= $withoutLf;
            $this->output .= $this->afterH2;
            $this->output .= PHP_EOL.PHP_EOL;
            $this->lastH2 = $withoutLf;
            $this->lastH3 = "";
            break;
          }
        case 2:
          if ($this->nheaders > 2) {
            $this->output .= PHP_EOL.PHP_EOL;
            $this->output .= $this->beforeH3;
            $this->output .= str_replace("\n", " / ", $node->title);
            $this->output .= $this->afterH3;
            $this->output .= PHP_EOL.PHP_EOL;
            $this->lastH3 = $withoutLf;
            break;
          }
        default:
          $this->output .= $indent;
          $this->output .= $withLf.PHP_EOL;
      }
      // Read children
      if (property_exists($node, "children")) {
        foreach($node->children->attached as $child) {
          $this->readNode($child, $level+1);
        }
      }
    }

    /**
     * Define the style of export (header style, file extension, line feed, etc.)
     * Use:
     * doku   for dokuwiki
     * md     for markdown
     */
    public function setStyle($style) {
      $this->style = $style;
      switch (strtolower($style)) {
        case "doku":
          $this->beforeH1 = "====== ";
          $this->afterH1 = " ======";
          $this->beforeH2 = "===== ";
          $this->afterH2 = " =====";
          $this->beforeH3 = "==== ";
          $this->afterH3 = " ====";
          $this->beforeH4 = "=== ";
          $this->afterH4 = " ===";
          $this->styleLf = " \\\\ ";
          $this->outputExt = ".txt";
          $this->preBold = "**";
          $this->postBold = "**";
          $this->preItalic = "//";
          $this->postItalic = "//";
          break;
        case "md":
          $this->beforeH1 = "# ";
          $this->afterH1 = "";
          $this->beforeH2 = "## ";
          $this->afterH2 = "";
          $this->beforeH3 = "### ";
          $this->afterH3 = "";
          $this->beforeH4 = "#### ";
          $this->afterH4 = "";
          $this->styleLf = "\\\n";
          $this->outputExt = ".md";
          $this->preBold = "**";
          $this->postBold = "**";
          $this->preItalic = "*";
          $this->postItalic = "*";
          break;
      }
    }


    /**
     * Return the indentation string to use according to the 
     * current $level and the requiered $maxHeaders (maximum number of headers)
     */
    private function _getLineIndent($level, $maxHeaders) {
      $i = $level - $maxHeaders + 1;
      if ($i < 0)
        $i = 1;
      return str_repeat("  ", $i)."* ";
    }

    /**
     * Extra code for pubmed2020 plugin and refnotes
     */
    public function createPubmedRefNotes() {
        // Get RefNotes params
        $params  = "<refnotes>".PHP_EOL;
        $params .= "  refnote-id       : 1".PHP_EOL;
        $params .= "  reference-base   : text".PHP_EOL;
        $params .= "  reference-font-weight : normal".PHP_EOL;
        $params .= "  reference-font-style : normal".PHP_EOL;
        $params .= "  reference-format : []".PHP_EOL;
        $params .= "  reference-group  : ,".PHP_EOL;
        $params .= "  reference-render : basic".PHP_EOL;
        $params .= "  multi-ref-id : note".PHP_EOL;
        $params .= "  note-preview : popup".PHP_EOL;
        $params .= "  notes-separator : none".PHP_EOL;
        $params .= "  note-text-align : left".PHP_EOL;
        $params .= "  note-font-size : normal".PHP_EOL;
        $params .= "  note-render : basic".PHP_EOL;
        $params .= "  note-id-base : text".PHP_EOL;
        $params .= "  note-id-font-weight : normal".PHP_EOL;
        $params .= "  note-id-font-style : normal".PHP_EOL;
        $params .= "  note-id-format : .".PHP_EOL;
        $params .= "  back-ref-caret : none".PHP_EOL;
        $params .= "  back-ref-base : text".PHP_EOL;
        $params .= "  back-ref-font-weight : bold".PHP_EOL;
        $params .= "  back-ref-font-style : normal".PHP_EOL;
        $params .= "  back-ref-format : none".PHP_EOL;
        $params .= "  back-ref-separator : ,".PHP_EOL;
        $params .= "  scoping : single".PHP_EOL;
        $params .= "</refnotes>".PHP_EOL.PHP_EOL.PHP_EOL;
        $params .= "{{pmid>doc_format:long}}".PHP_EOL.PHP_EOL.PHP_EOL;

        // Pattern to search for references
        $pattern = "/\[\(P(\d+)\)\]/";

        // Add all PMIDs references to dokuwiki/markdown output
        if (preg_match_all($pattern, $this->output, $matches, PREG_PATTERN_ORDER)) {
          echo "* Preparing REFNOTES with PubMed PMID: ".count($matches[1])." reference(s) found".PHP_EOL;
          
          // Get all PMIDs references
          $pmids = array_unique($matches[1]);
          sort($pmids, SORT_NUMERIC);
          // Add to raw output
          $this->output .= PHP_EOL.PHP_EOL.PHP_EOL."===== Références =====".PHP_EOL.PHP_EOL.PHP_EOL;
          $this->output .= PHP_EOL.PHP_EOL.$params;
          foreach($pmids as $k => $pmid) {
            $this->output .= "[(P".trim($pmid).">{{pmid>".trim($pmid)."}})]".PHP_EOL;
          }
          $this->output .= PHP_EOL.PHP_EOL."~~REFNOTES~~".PHP_EOL.PHP_EOL;
        }
    
        // Add all PMIDs references to RevealJS output
        if (preg_match_all($pattern, $this->rjs["output"], $matches, PREG_PATTERN_ORDER)) {
          echo "* Preparing REFNOTES with PubMed PMID (RevealJS): ".count($matches[1])." reference(s) found".PHP_EOL;
          
          // Get all PMIDs references
          $pmids = array_unique($matches[1]);
          sort($pmids, SORT_NUMERIC);
          $n = intdiv(count($pmids), 4) + 1;

          // Add this part to the map
          $this->rjs["map"] .= "  * Références bibliographiques".PHP_EOL;
          $this->rjs["output"] .= $this->rjs["mapTag"];

          // Create references slides
          $this->rjs["output"] .= "---- eric :1px.png bg-none none ---->".PHP_EOL;
          $this->rjs["output"] .= PHP_EOL;
          $this->rjs["output"] .= $this->beforeH2;
          $this->rjs["output"] .= "Références bibliographiques";
          $this->rjs["output"] .= $this->afterH2.PHP_EOL.PHP_EOL;
          $this->rjs["output"] .= "  * Nombre de références : ".count($pmids).PHP_EOL.PHP_EOL;
          $this->rjs["output"] .= "  * Nombre de slides : ".$n.PHP_EOL.PHP_EOL;
          $this->rjs["output"] .= "<----".PHP_EOL.PHP_EOL;

          // Add to RevealJS output
          $this->rjs["output"] .= PHP_EOL.PHP_EOL.$params;
          foreach($pmids as $k => $pmid) {
            $this->rjs["output"] .= "[(P".trim($pmid).">{{pmid>".trim($pmid)."}})]".PHP_EOL;
          }

          for($i=0; $i < $n; $i++) {
            $this->rjs["output"] .= PHP_EOL.PHP_EOL.PHP_EOL;
            $this->rjs["output"] .= "---- eric :1px.png bg-none none ---->".PHP_EOL;
            $this->rjs["output"] .= PHP_EOL;
            $this->rjs["output"] .= $this->beforeH3;
            $this->rjs["output"] .= "Références ".($i+1)." / ".$n;
            $this->rjs["output"] .= $this->afterH3.PHP_EOL.PHP_EOL;
            $this->rjs["output"] .= "<WRAP llo>~~REFNOTES 4~~</WRAP>".PHP_EOL;
            $this->rjs["output"] .= "<----".PHP_EOL.PHP_EOL;
          }
          $this->rjs["output"] .= PHP_EOL.PHP_EOL.PHP_EOL;
        }
    }
    
    /**
     * Save generated Dokuwiki ou Markdown output
     */
    public function saveOutput() {
      $outputFn = "";
      // Something to save?
      if (empty($this->output)) {
          echo "* No dokuwiki or markdown output to write".PHP_EOL;
      } else {
        $outputFn = $this->_getOutputFileName();
        file_put_contents($outputFn, $this->output);
        echo "* Writing dokuwiki or markdown output to: ".$outputFn.PHP_EOL;
      }
      if ($this->_hasRevealOutput()) {
        $outputFn = $this->_getOutputFileName(true);
        file_put_contents($outputFn, $this->_getRevealOutput());
        echo "* Writing RevealJS output to: ".$outputFn.PHP_EOL;
      } else {
          echo "* No RevealJS output to write".PHP_EOL;
      }
      echo "* Done".PHP_EOL;
      return true;
    }

    /**
     * Return the output filename for the constructed content.
     * $forReveal : set to true if you want the filename for the RevealJS part
     * \note This function uses the command line options to process the filename
     */
    private function _getOutputFileName($forReveal = false) {
      $outputFn = "";
      // Output specified in the command line options?
      if (array_key_exists("o", $this->options) &&
          !empty($this->options["o"])) {
        $outputFn = $this->options["o"];
      } else if (array_key_exists("f", $this->options) &&
          !empty($this->options["f"])) {
        // No output specified in the command line options -> use input filename
        $outputFn = $this->options["f"];
        $outputFn = $outputFn.$this->outputExt;
      }
      //if (empty($outputFn))
        // Here we go wrong! No output filename, no filename to process
      if ($forReveal) {
        $pos = strrpos($outputFn , '.');
        $outputFn = substr($outputFn, 0, $pos) . '_revealjs' . substr($outputFn, $pos);
      }
      return $outputFn;
    }

    /*******************************
     * RevealJS specific functions *
     *******************************/
    
    /**
     * Reveal Node starts with RJ / RJS / REVEAL / REVEALJS node title
     */
    private function _isRevealNode($node) {
      if (strtolower($node->title) === "rj" ||
          strtolower($node->title) === "rjs" ||
          strtolower($node->title) === "reveal" ||
          strtolower($node->title) === "revealjs"
          ) {
          return true;
       }
       return false;
    }

    private function _readRevealNode($node, $level = 0) {
      // If _isRevealNode
      if ($this->_isRevealNode($node)) {
        // Here $node is pointing to the RJS root node not the content of the slide
        // Add last H2 (H1 is the title of the map)
        $this->rjs["currentSlide"] .= PHP_EOL.PHP_EOL.PHP_EOL;
        $this->rjs["currentSlide"] .= $this->_getSlideBefore().PHP_EOL;
        $this->rjs["currentSlide"] .= $this->beforeH2;
        $this->rjs["currentSlide"] .= $this->lastH2;

        // Add last H3 if exists
        if (!empty($this->lastH3) && $level > 2)
          $this->rjs["currentSlide"] .= " - ".$this->lastH3;
        $this->rjs["currentSlide"] .= $this->afterH2;
        $this->rjs["currentSlide"] .= PHP_EOL.PHP_EOL.PHP_EOL;
        $this->rjs["currentSlideEmpty"] = true;
        $level = 0;
      } else {

        // Check commands : Titre, Auteurs, Affiliation, Années, Footer
        if ($this->_isRevealNodeCommand($node)) {
          $this->_readRevealNodeCommand($node, $level);
          return true;
        }

        // No text => add a line-break
        if (empty($node->title)) {
          $this->rjs["currentSlide"] .= PHP_EOL.$this->styleLf.PHP_EOL;
        } else {
          // Is background mention?
          if ($node->title === "background" || 
            $node->title === "bg") {
            // Get values (first child)
            if (property_exists($node, "children") &&
              property_exists($node->children, "attached")) {
              $bg = $node->children->attached[0]->title;
              $this->rjs["currentSlide"] = str_replace(":1px.png", $bg, $this->rjs["currentSlide"]);
              $this->rjs["currentSlideEmpty"] = false;
              return true;
            }
          } else if ($node->title === "option" || 
            $node->title === "opt") {
            // Read slide options
            //     no-footer
            //     no-title
            // Get values (first child)
            if (property_exists($node, "children") &&
              property_exists($node->children, "attached")) {
              foreach($node->children->attached as $o) {
                $option = $o->title;
                switch ($option) {
                  case "no-footer":
                    // TODO: improve this with regex
                    $option = " ".$option." ";
                    $this->rjs["currentSlide"] = str_replace("---- ", "---- ".$option, 
                                                             $this->rjs["currentSlide"]);
                    break;
                  case "no-title":
                    $pattern = "/^(".$this->beforeH2.")(.*)(".$this->afterH2.")$/m";
                    $rep     = "\\1 \\3";
                    $this->rjs["currentSlide"] = preg_replace($pattern, $rep, 
                                                              $this->rjs["currentSlide"], 1);
                    break;
                }
                $this->rjs["currentSlideEmpty"] = false;
              }
              return true;
            }
          }

          // Add content as a list. Note: with reveal we only keep one header level
          $indent = $this->_getLineIndent($level, 1);
          // Convert LF to dokuwiki/markdown style
          $withLf = str_replace("\n", $this->styleLf, $node->title);
          
          // <wrap dugp_red></wrap> a part of the text?
          $pattern = "/([^|]*)\|\|([^|]*)\|\|(.*)/";
          if (preg_match($pattern, $withLf, $matches)) {
            $withLf = preg_replace($pattern, "\\1 <wrap dugp_red>**\\2**</wrap> \\3", $withLf);
          }
          // <wrap dugp_red></wrap> all title?
          if (property_exists($node, "markers")) {
            if (count($node->markers)) {
              for($i=0; $i<count($node->markers); $i++) {
                if (property_exists($node->markers[$i], "markerId") &&
                    $node->markers[$i]->markerId === "tag-red") {
                  $withLf = "<wrap dugp_red>**".$withLf."**</wrap>";
                }
                // tag-⚫ flag-⚑ star-★ people-☻  -> red orange dark-blue blue dark-purple green grey
                // task-  -> start oct 3oct half 5oct 7oct done
                // arrow- -> left⇐ right⇒ up⇑ down⇓ left-right⇔ up-down⇕ refresh↻
                // c_symbol_quote c_symbol_apostrophe symbol-question
                //$arr = get_object_vars($node->style->properties);
              }
            }
          }

          // Get style of the node
          if (property_exists($node, "style")
            && property_exists($node->style, "properties")) {
            $arr = get_object_vars($node->style->properties);

            // Bold?
            if (array_key_exists("fo:font-weight", $arr) &&
                !empty($arr["fo:font-weight"])) {
              if ($arr["fo:font-weight"] === "bold") {
              $withLf = $this->preBold.$withLf.$this->postBold;
              }
            } else             
            // Italic?
            if (array_key_exists("fo:font-style", $arr) &&
                !empty($arr["fo:font-style"])) {
              if ($arr["fo:font-style"] === "italic") {
              $withLf = $this->preItalic.$withLf.$this->postItalic;
              }
            }
          }
          // TODO: Get markers markers [0] markerId 
          if (property_exists($node, "markers")) {
            //echo print_r($node->markers);
            // tag-⚫ flag-⚑ star-★ people-☻  -> red orange dark-blue blue dark-purple green grey
            // task-  -> start oct 3oct half 5oct 7oct done
            // arrow- -> left⇐ right⇒ up⇑ down⇓ left-right⇔ up-down⇕ refresh↻
            // c_symbol_quote c_symbol_apostrophe symbol-question
            //$arr = get_object_vars($node->style->properties);
          }
        
          $this->rjs["currentSlide"] .= $indent.$withLf.PHP_EOL;
        }

        // This slide is not empty
        $this->rjs["currentSlideEmpty"] = false;
      }

      // Read children
      if (property_exists($node, "children")) {
        foreach($node->children->attached as $child) {
          $this->_readRevealNode($child, $level+1);
        }
      }

      // Add currentSlide to output with the final slide block
      if ($level === 0) {
        if (!$this->rjs["currentSlideEmpty"]) {
          // Create presentation map
          if (strpos($this->rjs["map"], $this->lastH2) === false) {
            $this->rjs["output"] .= $this->rjs["mapTag"];
            $this->rjs["map"] .= "  * ".$this->lastH2.PHP_EOL;
          }
          $this->rjs["output"] .= $this->rjs["currentSlide"];
          $this->rjs["output"] .= $this->_getSlideAfter().PHP_EOL;
        }
        // Reset vars
        $this->rjs["currentSlide"] = "";
        $this->rjs["currentSlideEmpty"] = true;
      }
    }

    private function _getSlideBefore() {
      $bgAnim = "";
      $style = "";
      $slideTr = "";
      if (array_key_exists("bg-anim", $this->rjs) &&
          !empty($this->rjs["bg-anim"]))
        $bgAnim = $this->rjs["bg-anim"];
      if (array_key_exists("style", $this->rjs) &&
          !empty($this->rjs["style"]))
        $style = $this->rjs["style"];
      if (array_key_exists("slide-transition", $this->rjs) &&
          !empty($this->rjs["slide-transition"]))
        $slideTr = $this->rjs["slide-transition"];

      return "---- ".$style." :1px.png ".$bgAnim." ".$slideTr." ---->".PHP_EOL;
    }

    private function _getSlideAfter() {
      return "<----".PHP_EOL.PHP_EOL;
    }

    private function _hasRevealOutput() {
      if (array_key_exists("output", $this->rjs) &&
          !empty($this->rjs["output"]))
        return true;
      return false;
    }

    private function _getRevealOutput() {    
      if (array_key_exists("output", $this->rjs) &&
          !empty($this->rjs["output"])) {
        $this->_getPresentationMap();
        return $this->__prepareRevealOutput();
      }
      return "";
    }

    private function _isRevealNodeCommand($node) {
      if (in_array(strtolower($node->title), $this->rjs["commands"])) {
        return true;
      }
      return false;
    }

    private function _readRevealNodeCommand($node, $level) {
      if (!$this->_isRevealNodeCommand($node))
        return true;
      $this->rjs[strtolower($node->title)] = $node->children->attached[0]->title;
      return true;
    }

    /**
     * Prepare the plan slide
     */
    private function _getPresentationMap() {
      if (empty($this->rjs["map"]))
        return "";
      $s  = "---- eric fr:medical:cours:dugp_memoires:sitemap_rouge.svg ";
      $s .= "20% contain center right 35% bg-none none ---->".PHP_EOL;
      $s .= "===== Plan de la présentation =====".PHP_EOL.PHP_EOL;
      $s .= "<WRAP dugp_plan>".PHP_EOL;
      $s .= $this->rjs["map"];
      $s .= "</WRAP>";
      $this->rjs["output"] = str_replace($this->rjs["mapTag"], $s ,$this->rjs["output"]);

      // Highlight title
      $a = explode("<WRAP dugp_plan>", $this->rjs["output"]);
      for($i=0; $i<count($a); $i++) {
        if ($i === 0) {
          $this->rjs["output"] = $a[$i];
          continue;
        }
        // Get first title (title is "H2" or "H2 - H3")
        $pattern = "/^".trim($this->beforeH2)."([^-]*).*".trim($this->afterH2)."$/m";
        if (preg_match($pattern, $a[$i], $matches)) {
          $h2 = trim($matches[1]);
          //echo $h2.PHP_EOL;
          $a[$i] = preg_replace("/\* ".$h2."/", "* **".$h2."**", $a[$i], 1);
          $this->rjs["output"] .= "<WRAP dugp_plan>".$a[$i];
        } else {
          $this->rjs["output"] .= "<WRAP dugp_plan>".$a[$i];
        }
      }
    }

    private function __prepareRevealOutput() {
      if (empty($this->rjs["output"]))
        return "";
      $final = "";
      // Add document begining
      $final .= $this->rjs["outputStart"];
      // Add footer
      $final .= sprintf($this->rjs["footerFormat"], $this->rjs["footer"]);
      
      // Create first slide
      // Style
      $s = "---- eric :1px.png bg-none none no-footer ---->".PHP_EOL;
      $final .= sprintf($s, $this->rjs["style"]);
      // Titre
      // titrecourt
      $s = $this->beforeH1."%s".$this->afterH1.PHP_EOL.PHP_EOL;
      $final .= sprintf($s, $this->rjs["titre"]);
      // auteurs
      $s = "<WRAP name_red>%s</WRAP>".PHP_EOL.PHP_EOL;
      $withLf = str_replace("\n", $this->styleLf, $this->rjs["auteurs"]);
      $final .= sprintf($s, $withLf);
      // affiliation
      $s = "<WRAP name_place>%s</WRAP>".PHP_EOL.PHP_EOL;
      $withLf = str_replace("\n", $this->styleLf, $this->rjs["affiliation"]);
      $final .= sprintf($s, $withLf);
      // date
      $s = "<WRAP date>%s</WRAP>".PHP_EOL.PHP_EOL;
      $withLf = str_replace("\n", $this->styleLf, $this->rjs["date"]);
      $final .= sprintf($s, $withLf);
      // bandeau
      $s = "<WRAP first_footer>{{ %s?nolink }}</WRAP>".PHP_EOL.PHP_EOL;
      $final .= sprintf($s, $this->rjs["bandeau"]);
      //$final .= "<---- ".PHP_EOL;

      // Add computed slides
      $final .= $this->rjs["output"];

      // Add extrastyle
      if (array_key_exists("extrastyle", $this->rjs) &&
          !empty($this->rjs["extrastyle"]))
        $final .= $this->rjs["extrastyle"].PHP_EOL.PHP_EOL;

      // Add document ending
      $final .= $this->rjs["outputEnd"];
      
      // Send all to the revealjs output
      $this->rjs["output"] = $final;
      return $this->rjs["output"];
    }

}

/**
 * Basic script 
 * (c) Eric Maeker, MD, France, https://github.com/EricMaeker
 */
$xmd = new XMindToMD();
$xmd->runExport();
$xmd->createPubmedRefNotes();
$xmd->saveOutput();
exit(0);

?>

