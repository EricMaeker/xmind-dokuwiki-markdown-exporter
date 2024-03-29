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
    private $formatRefs = "long";  // Format for the reference output -> {{format>...}}
    private $extraRefs = Array();  // Read refs from a specific page
    private $manualRefs = Array();  // Extracted refs from map [(...>...)]
    private $currentOptions = Array();  // Contains options of a branch

    // Reveal JS var
    private $rjs = Array(  
      "commands" => Array(
         "titre",
         "titrecourt",
         "titrelong",
         "auteurs",
         "affiliation",
         "date",
         "citation",
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
      "currentSlideOpt" => "",  // Some options for the currentSlide
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
    private $beforeH5 = "";
    private $afterH5 = "";
    private $nheaders = 2;       // Number of header levels 
    private $onlyBranch = -1;    // Process only one branch
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
        $shortopts .= "l::"; // level (optional)
        $shortopts .= "b::"; // one branch only (optional)
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
          echo "    -b   Only branch number x".PHP_EOL;
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

        if (array_key_exists("b", $this->options) &&
            !empty($this->options["b"])) {
          $this->onlyBranch = $this->options["b"];
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
        echo "* Decoding XMind JSON content".PHP_EOL;
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
      if (!$this->_isNodeToRead($node))
        return true;
      // Don't read extrapage, only reference nodes
      if ($this->_isExtraPage($node) ||
          $this->_isOnlyReference($node)) {
        return true;
      }
      if ($this->_isRevealNode($node)) {
        $this->_readRevealNode($node, $level);
        return true;
      }
      if ($this->_isTableNode($node)) {
        $this->_readTableNode($node, $level);
        return true;
      }
      if ($this->_isExtraDokuwikiNode($node)) {
        // TODO: add this to revealJS reader
        $this->_readExtraDokuwikiNode($node, $level);
        return true;
      }
      if ($this->_isOptionsNode($node)) {
        return true;
      }

      // Manage option "only-tagged" == only process tagged nodes
      if (in_array("only-tagged", $this->currentOptions)) {
        if (property_exists($node, "markers") && count($node->markers)) {
          // Process node
//           echo PHP_EOL.PHP_EOL."tagg ok ".print_r($node->title).PHP_EOL.PHP_EOL;
        } else {
          // Do not process node
//           echo PHP_EOL.PHP_EOL."read children ok ".print_r($node->title).PHP_EOL.PHP_EOL;
          // Read children
          if (property_exists($node, "children")) {
             foreach($node->children->attached as $child) {
               $this->readNode($child, $level+1);
             }
          }
          return true;
        }
      }

      // Get some work on node text
      $title = $node->title;
      $nheaders_bkup = $this->nheaders;

      // Title contains a mention to nb of levels? {{l\d}}
      if (preg_match("/.*\{\{l(\d)\}\}/", $title, $matches)) {
        $this->nheaders = $matches[1];
        $title = preg_replace("/(.*)\s*\{\{l(\d)\}\}/", "$1", $title);
      }

      // Convert LF to dokuwiki/markdown style
      $withoutLf = str_replace("\n", " / ", $title);
      $withLf = str_replace("\n", $this->styleLf, $title);

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
            $this->output .= str_replace("\n", " / ", $title);
            $this->output .= $this->afterH3;
            $this->output .= PHP_EOL.PHP_EOL;
            $this->lastH3 = $withoutLf;
            break;
          }
        case 3:
          if ($this->nheaders > 3) {
            $this->output .= PHP_EOL.PHP_EOL;
            $this->output .= $this->beforeH4;
            $this->output .= str_replace("\n", " / ", $title);
            $this->output .= $this->afterH4;
            $this->output .= PHP_EOL.PHP_EOL;
            $this->lastH3 = $withoutLf;
            break;
          }
        case 4:
          if ($this->nheaders > 4) {
            $this->output .= PHP_EOL.PHP_EOL;
            $this->output .= $this->beforeH5;
            $this->output .= str_replace("\n", " / ", $title);
            $this->output .= $this->afterH5;
            $this->output .= PHP_EOL.PHP_EOL;
            $this->lastH3 = $withoutLf;
            break;
          }
        default:
          // Manage indentation / list
          if (in_array("no-list", $this->currentOptions)) {
            $indent = $this->styleLf.$this->styleLf;
          } else {
            $indent = $this->_getLineIndent($level, $this->nheaders);
          }

          // Add to general output
          $this->output .= $indent;

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
          $this->output .= $withLf.PHP_EOL;
      }

      // Read children
      if (property_exists($node, "children")) {
        foreach($node->children->attached as $child) {
          $this->readNode($child, $level+1);
        }
      }

      $this->nheaders = $nheaders_bkup;
      
//       echo "Removing opt";
//       unset($this->currentOptions);
//       $this->currentOptions = array();
    }


     /**
     * Ok
     * Return false if node title contains {{-}}
     */
   private function _isNodeToRead($node) {
      $m = array();
      if (preg_match("/.*\{\{\-\}\}/", $node->title, $m)) {
        return false;
      }
      return true;
    }

    /**
     * Ok
     * TODO: catch previous node?
     */
    private function _isTableNode($node) {
//       if (!property_exists($node, "structureClass"))
//         return false;
//       if (empty($node->structureClass) ||
//           $node->structureClass !== "org.xmind.ui.spreadsheet.column")
//         return false;
      // Use Dokuwiki format to detect table node
      // Table should start with headings -> ^
      $test = str_replace(" ", "", $node->title);
      if (substr($test, 0, 1) === "^" || substr($test, 0, 1) === "|")
          return true;
      return false;
    }

    /**
     * Add a dokuwiki formatted table in the output document or the revealJS output
     */
    private function _readTableNode($node, $level = 0, $isReveal = false) {
      if (!$this->_isTableNode($node))
        return false;
      if ($isReveal) {
        $this->rjs["currentSlide"] .= PHP_EOL.PHP_EOL.$node->title.PHP_EOL.PHP_EOL;
      } else {
        $this->output .= PHP_EOL.PHP_EOL.$node->title.PHP_EOL.PHP_EOL;
      }
    }

    /**
     * Check if the node is a dokuwiki extra command like
     * <TEXT <WRAP <wrap <HTML <style ...
     */
    private function _isExtraDokuwikiNode($node) {
      // Check first chars
      $test = str_replace(" ", "", $node->title);
      $test = substr($test, 0, 5);
      $tags = array("<HTML", "<styl", "<wrap", "<WRAP", "<scri", "<TEXT", "<text");
      return in_array($test, $tags);
    }

    /**
     * Add a dokuwiki formatted table in the output document or the revealJS output
     */
    private function _readExtraDokuwikiNode($node, $level = 0, $isReveal = false) {
      if (!$this->_isExtraDokuwikiNode($node))
        return false;
      if ($isReveal) {
        $this->rjs["currentSlide"] .= PHP_EOL.PHP_EOL.$node->title.PHP_EOL.PHP_EOL;
      } else {
        $this->output .= PHP_EOL.PHP_EOL.$node->title.PHP_EOL.PHP_EOL;
      }
    }


    /**
     * Read options from textual output inside the map. Options are
     * - only-bold : keep only bold titles
     * - only-tagged : keep only tagged titles
     * - no-indent / no-list : everything in the branch will not be extracted as an indent list
     */
    private function _isOptionsNode($node) {
      if (strtolower($node->title) == "opt" 
          || strtolower($node->title) == "option"
          || strtolower($node->title) == "options") {
        // Read children
        if (property_exists($node, "children")) {
          foreach($node->children->attached as $child) {
            switch ($child->title) {
            case "no-list": case "no-indent":
              array_push($this->currentOptions, "no-list");
              break;
            case "only-bold":
              array_push($this->currentOptions, "only-bold");
              break;
            case "only-tagged":
              array_push($this->currentOptions, "only-tagged");
              break;
            }
          }
        }
        return true;
      }
      return false;
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
          $this->beforeH5 = "== ";
          $this->afterH5 = " ==";
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
          $this->beforeH5 = "##### ";
          $this->afterH5 = "";
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
        echo "* Preparing REFNOTES".PHP_EOL;
        $includeRefs = false; // No references to add if false

        // Get RefNotes params
        $refs  = "<refnotes>".PHP_EOL;
        $refs .= "  refnote-id       : 1".PHP_EOL;
        $refs .= "  reference-base   : text".PHP_EOL;
        $refs .= "  reference-font-weight : normal".PHP_EOL;
        $refs .= "  reference-font-style : normal".PHP_EOL;
        $refs .= "  reference-format : []".PHP_EOL;
        $refs .= "  reference-group  : ,".PHP_EOL;
        $refs .= "  reference-render : basic".PHP_EOL;
        $refs .= "  multi-ref-id : note".PHP_EOL;
        $refs .= "  note-preview : popup".PHP_EOL;
        $refs .= "  notes-separator : none".PHP_EOL;
        $refs .= "  note-text-align : left".PHP_EOL;
        $refs .= "  note-font-size : normal".PHP_EOL;
        $refs .= "  note-render : basic".PHP_EOL;
        $refs .= "  note-id-base : text".PHP_EOL;
        $refs .= "  note-id-font-weight : normal".PHP_EOL;
        $refs .= "  note-id-font-style : normal".PHP_EOL;
        $refs .= "  note-id-format : .".PHP_EOL;
        $refs .= "  back-ref-caret : none".PHP_EOL;
        $refs .= "  back-ref-base : text".PHP_EOL;
        $refs .= "  back-ref-font-weight : bold".PHP_EOL;
        $refs .= "  back-ref-font-style : normal".PHP_EOL;
        $refs .= "  back-ref-format : none".PHP_EOL;
        $refs .= "  back-ref-separator : ,".PHP_EOL;
        $refs .= "  scoping : single".PHP_EOL;
        $refs .= "</refnotes>".PHP_EOL.PHP_EOL;
        $refs .= "{{pmid>doc_format:".$this->formatRefs."}}".PHP_EOL.PHP_EOL;

        // Include reference pages "{{refs>...}}" in xmind file
        if (count($this->extraRefs)) {
          $includeRefs = true;
          echo "    * Found ".count($this->extraRefs)." references' page(s)".PHP_EOL;
          foreach($this->extraRefs as $page) {
            if (strpos($page, "&nofooter&link") === false) {
              $page = str_replace("}}", "&nofooter&link}}", $page);
            }
            $refs .= str_replace("{{refs>", "{{page>", $page).PHP_EOL;
          }
          $refs .= PHP_EOL.PHP_EOL;
        }

        // Search for references PMID references (noted [(P12345678)] in xmind file)
        $pattern = "/\[\(P(\d+)\)\]/";
        if (preg_match_all($pattern, $this->output.$this->rjs["output"], $matches, PREG_PATTERN_ORDER)) {
          $includeRefs = true;
          // Get all PMIDs references
          $pmids = array_unique($matches[1]);
          sort($pmids, SORT_NUMERIC);
          echo "    * Found ".count($pmids)." Unique PubMed PMID reference(s)".PHP_EOL;          
          // Add to raw output
          foreach($pmids as $k => $pmid) {
            $refs .= "[(P".trim($pmid).">{{pmid>".trim($pmid)."}})]".PHP_EOL;
          }
          $refs .= PHP_EOL.PHP_EOL;
        }

//        } else if (preg_match('~{{pmidlist}}~', $node->title, $m)) {
//           return true;
//        }

        // Add $this->manualRefs
        if (count($this->manualRefs)) {
          $includeRefs = true;
          echo "    * Found ".count($this->manualRefs)." Unique manual node reference(s)".PHP_EOL;          
          foreach($this->manualRefs as $r) {
            $refs .= $r.PHP_EOL;
            // Search for manual references (noted [(nameOfRef>Value Of the ref. https://link.lk.)])
//            $contents = file_get_contents($fn);
//            $pattern = "/(\[\([^>]+\>.*\)\])/";
//            if (preg_match_all($pattern, $this->output.$this->rjs["output"], $matches, PREG_PATTERN_ORDER)) {
//              $includeRefs = true;
//              // Include these references
//              $r = array_unique($matches[1]);
//              sort($r, SORT_STRING);
//              echo "    * Found ".count($r)." Unique manual reference(s) in text".PHP_EOL;          
//              // Add to raw output
//              foreach($r as $ref) {
//                $refs .= $ref.PHP_EOL;
//              }
//            }
          }
        }


        // Add references to outputs (raw and RevealJS)
        if ($includeRefs == true) {
          // Add to raw
          echo "    * Add references to dokuwiki/markdown output".PHP_EOL;          
          $this->output .= PHP_EOL.PHP_EOL.PHP_EOL."===== Références =====".PHP_EOL.PHP_EOL.PHP_EOL;
          $this->output .= $refs.PHP_EOL;
          $this->output .= PHP_EOL.PHP_EOL."~~REFNOTES~~".PHP_EOL.PHP_EOL;
          
          // Add to RevealJS
          if (!empty($this->rjs["output"])) {
            // Count number of reference used in the slides output
            $n = 0;
            $pattern = "/\[\([^\)^>]*\)\]/";
            if (preg_match_all($pattern, $this->rjs["output"], $matches)) {
              $n = count($matches[0]);
              $a = Array();
              foreach($matches[0] as $aref) {
                if (in_array($aref, $a))
                  continue;
                array_push($a, $aref);
              }
              $n = count($a);
            }
            echo "    * Add references to RevealJS output? Number used: ".$n.PHP_EOL;

            // If we have refs to add
            if ($n > 0) {
              // Four refs by references slides
              $nSlides = intdiv($n, 4);
              if (($n % 4) > 0)  $nSlides++;

              // Add this part to the map
              $this->rjs["map"] .= "  * Références bibliographiques".PHP_EOL;
              $this->rjs["output"] .= $this->rjs["mapTag"].PHP_EOL.PHP_EOL;

              // Create references slides
              $this->rjs["output"] .= "---- eric :1px.png bg-none none ---->".PHP_EOL;
              $this->rjs["output"] .= PHP_EOL;
              $this->rjs["output"] .= $this->beforeH2;
              $this->rjs["output"] .= "Références bibliographiques";
              $this->rjs["output"] .= $this->afterH2.PHP_EOL.PHP_EOL;
              $this->rjs["output"] .= "  * Nombre de références : ".$n.PHP_EOL.PHP_EOL;
              $this->rjs["output"] .= "  * Nombre de slides : ".$nSlides.PHP_EOL.PHP_EOL; 

              // Add to RevealJS output
              $this->rjs["output"] .= PHP_EOL.PHP_EOL.$refs.PHP_EOL;
              $this->rjs["output"] .= "<----".PHP_EOL.PHP_EOL; 

              for($i=0; $i < $nSlides; $i++) {
                $this->rjs["output"] .= PHP_EOL.PHP_EOL.PHP_EOL;
                $this->rjs["output"] .= "---- eric :1px.png bg-none none ---->".PHP_EOL;
                $this->rjs["output"] .= PHP_EOL;
                $this->rjs["output"] .= $this->beforeH3;
                $this->rjs["output"] .= "Références ".($i+1)." / ".$nSlides;
                $this->rjs["output"] .= $this->afterH3.PHP_EOL.PHP_EOL;
                $this->rjs["output"] .= "<WRAP references>~~REFNOTES 4~~</WRAP>".PHP_EOL;
                $this->rjs["output"] .= "<----".PHP_EOL.PHP_EOL;
              }
              $this->rjs["output"] .= PHP_EOL.PHP_EOL.PHP_EOL;
            }
          }
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

    /**
     * Add a dokuwiki page {{page>....}}
     */
    private function _isExtraPage($node) {
      if (strtolower(substr($node->title, 0, 7) === "{{page>")) {
          // TODO: problem here (inclure à l'intérieur d'un slide si ce sont les réfs)
          //echo print_r($node);
          $this->output .= $node->title.PHP_EOL;
          $this->rjs["output"] .= PHP_EOL.$node->title.PHP_EOL;
          return true;
       } else if (strtolower(substr($node->title, 0, 7) === "{{refs>")) {
          array_push($this->extraRefs, $node->title);
          return true;
       } else if (preg_match('~{{format>(.+)}}~', $node->title, $m)) {
          $this->formatRefs =$m[1];
          return true;
//        } else if (preg_match('~{{pmidlist}}~', $node->title, $m)) {
//           return true;
       }
       return false;
    }

    /**
     * Add a dokuwiki page {{page>....}}
     */
    private function _isOnlyReference($node) {
        $pattern = "/^\[\([^>]+\>.*\)\]$/m";
        if (preg_match($pattern, $node->title, $matches)) {
          array_push($this->manualRefs, $node->title);
          return true;
        }
        return false;
    }

    /**
     * Find all PMID references in the xmind processed output
     * Note that the output must be created before calling this function
     */
    private function _extractAllPmidReferences() {
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
      // Don't read extrapage, only reference nodes
      if ($this->_isExtraPage($node) ||
          $this->_isOnlyReference($node)) {
        return true;
      }
      // Table node
      if ($this->_isTableNode($node)) {
        $this->_readTableNode($node, $level, true);
        return true;
      }
      // If _isRevealNode
      if ($this->_isRevealNode($node)) {
        // Here $node is pointing to the RJS root node not the content of the slide

        // Check Major RJS red mark   ±   add to $this->rjs["currentSlideOpt"]
        if (property_exists($node, "markers")) {
          if (count($node->markers)) {
            for($i=0; $i<count($node->markers); $i++) {
              if (property_exists($node->markers[$i], "markerId") &&
                  $node->markers[$i]->markerId === "tag-red") {
                $this->rjs["currentSlideOpt"] = "tag-red";
              }
            }
          }
        }

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
        $this->rjs["currentSlideNotes"] = "";
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
          switch (strtolower($node->title)) {
            case "background": case "bg":
              // Get values (first child)
              if (property_exists($node, "children") &&
                property_exists($node->children, "attached")) {
                $bg = $node->children->attached[0]->title;
                $this->rjs["currentSlide"] = str_replace(":1px.png", $bg, $this->rjs["currentSlide"]);
                $this->rjs["currentSlideEmpty"] = false;
                return true;
              }
              break;
            case "notes" : case "note" :
              // Slide Notes
              if (property_exists($node, "children") &&
                property_exists($node->children, "attached")) {
                // Read all children
                foreach($node->children->attached as $noteNode) {
                  if (property_exists($noteNode, "title"))
                    $this->rjs["currentSlideNotes"] .= "  * ".$noteNode->title.PHP_EOL;
                }
                $this->rjs["currentSlideEmpty"] = false;
                return true;
              }
              break;
            case "option": case "opt":
              // Read slide options
              //     no-footer
              //     no-title
              //     no-list
              //     start_map_here
              // Get values (first child)
              if (property_exists($node, "children") &&
                property_exists($node->children, "attached")) {
                foreach($node->children->attached as $o) {
                  $option = $o->title;
                  switch ($option) {
                    case "no-ul": case "no-li": case "no-list":
                      $this->rjs["currentSlideOpt"] = "no-list";
                    break;
                    case "no-footer":
                      // TODO: improve this with regex
                      $option = " ".$option." ";
                      $this->rjs["currentSlide"] = str_replace("---- ", "---- ".$option, 
                                                               $this->rjs["currentSlide"]);
                      break;
                    case "no-title":
                      $pattern = "/^(".$this->beforeH2.")(.*)(".$this->afterH2.")$/m";
                      // $rep     = "\\1 \\3";
                      $rep     = "";
                      $this->rjs["currentSlide"] = preg_replace($pattern, $rep, 
                                                                $this->rjs["currentSlide"], 1);
                      break;
                    case "start_map_here":
                      // Remove all map tag already in output
                      $this->rjs["output"] = str_replace($this->rjs["mapTag"], "", $this->rjs["output"]);
                      $this->rjs["map"] = "";
                     break; 
                 } 
                 $this->rjs["currentSlideEmpty"] = false;
               }
               return true;
             }
             break;
           }  // End Switch

          // Add content as a list. Note: with reveal we only keep one header level
          // Only if text does not start with <TEXT or <WRAP
          if (substr($node->title, 0, 5) !== "<TEXT" && 
              substr($node->title, 0, 5) !== "<WRAP")
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
          //if (property_exists($node, "markers")) {
            //echo print_r($node->markers);
            // tag-⚫ flag-⚑ star-★ people-☻  -> red orange dark-blue blue dark-purple green grey
            // task-  -> start oct 3oct half 5oct 7oct done
            // arrow- -> left⇐ right⇒ up⇑ down⇓ left-right⇔ up-down⇕ refresh↻
            // c_symbol_quote c_symbol_apostrophe symbol-question
            //$arr = get_object_vars($node->style->properties);
          //}
        
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
          // Add slide contents
          $current = $this->rjs["currentSlide"];
          //  Check for options
          if (strpos($this->rjs["currentSlideOpt"], "tag-red") !== false) {
            //echo PHP_EOL.PHP_EOL.$this->rjs["currentSlideOpt"].PHP_EOL.PHP_EOL;
            // Remove list
            $pattern = "/\s+\*/";
            if (preg_match($pattern, $current, $matches, PREG_OFFSET_CAPTURE)) {
              $current = preg_replace($pattern, "\n\\\\\ ", $current);
              // Remove the two first \\ char
              $idx = $matches[0][1]+1;
              $current = substr($current, 0, $idx).
                         "\n<WRAP dugp_red_slide>".
                         substr($current, $idx+3, -1).
                         "</WRAP>\n";
            }
            //echo PHP_EOL.PHP_EOL."****".$current."****".PHP_EOL.PHP_EOL;
            
          } else if (strpos($this->rjs["currentSlideOpt"], "no-list") !== false) {
            $pattern = "/\s+\*/";
            if (preg_match($pattern, $current, $matches)) {
              $current = preg_replace($pattern, "\n", $current);
            }
          }
          $this->rjs["output"] .= $current;
          // Add notes
          if (!empty($this->rjs["currentSlideNotes"])) {
            $this->rjs["output"] .= PHP_EOL.PHP_EOL.PHP_EOL;
            $this->rjs["output"] .= "<notes>".PHP_EOL;
            $this->rjs["output"] .= $this->rjs["currentSlideNotes"].PHP_EOL;
            $this->rjs["output"] .= "</notes>".PHP_EOL;
          }
          // Close slide
          $this->rjs["output"] .= $this->_getSlideAfter().PHP_EOL;
        }
        // Reset vars
        $this->rjs["currentSlide"] = "";
        $this->rjs["currentSlideNotes"] = "";
        $this->rjs["currentSlideEmpty"] = true;
        $this->rjs["currentSlideOpt"] = "";
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

      // TODO: Notice: Trying to access array offset on value of type null in /Users/eric/Documents/git/xmindtomd.php on line 965

      $this->rjs[strtolower($node->title)] = $node->children->attached[0]->title;
      return true;
    }

    /**
     * Prepare the plan slide
     */
    private function _getPresentationMap() {
      if (empty($this->rjs["map"]))
        return "";
      $s  = PHP_EOL.PHP_EOL;
      $s .= "---- eric fr:medical:cours:dugp_memoires:sitemap_rouge.svg ";
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

        // TODO: when user apply "no-title" opt to the slide, script is unable to find h2 title

        // Get first title (title is "H2" or "H2 - H3")
        $pattern = "/^".trim($this->beforeH2)."([^-]*).*".trim($this->afterH2)."$/m";

        // echo $i.PHP_EOL;
        // echo $pattern.PHP_EOL;        
        // echo $a[$i].PHP_EOL.PHP_EOL.PHP_EOL.PHP_EOL;
        
        if (preg_match($pattern, $a[$i], $matches)) {
        
        // echo print_r($matches[1]);
        
          $h2 = trim($matches[1]);
          $h2 = str_replace("/", "&sol;", $h2);
          //echo $h2.PHP_EOL;
          $a[$i] = preg_replace("/\* ".$h2."/", "* **-> ".$h2."**", $a[$i], 1);
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
      if (array_key_exists("footer", $this->rjs) &&
          !empty($this->rjs["footer"])) {
        $footer = $this->rjs["footer"];
      } else {
        $footer = "";
      }      
      $final .= sprintf($this->rjs["footerFormat"], $footer);
      
      // Create first slide
      // Style
      $s = "---- %s :1px.png bg-none none no-footer ---->".PHP_EOL;
      if (array_key_exists("style", $this->rjs) &&
          !empty($this->rjs["style"])) {
        $style = $this->rjs["style"];
      } else {
        $style = "eric";
      }      
      $final .= sprintf($s, $style);

      // Titre
      // titrecourt
      $s = $this->beforeH1."%s".$this->afterH1.PHP_EOL.PHP_EOL;
      if (array_key_exists("titre", $this->rjs) &&
          !empty($this->rjs["titre"])) {
        $title = $this->rjs["titre"];
      } else {
        $title = "No title";
      }      
      $final .= sprintf($s, $title);

     // Auteurs, affiliation, date, citation
      $elements = Array(  // Key = wrap style    --   Value = RJS command content
        "name_red" => "auteurs",
        "name_place" => "affiliation",
        "date" => "date",
        "citation" => "citation",
      );
      foreach($elements as $k => $v) {
        // Nothing to do?
        if (!array_key_exists($v, $this->rjs) || 
            empty($this->rjs[$v]))
            continue;
        // Add wrap and content
        $s = "<WRAP ".$k.">%s</WRAP>".PHP_EOL.PHP_EOL;
        $withLf = str_replace("\n", $this->styleLf, $this->rjs[$v]);
        $final .= sprintf($s, $withLf);
      }

      // bandeau
      if (array_key_exists("bandeau", $this->rjs) &&
          !empty($this->rjs["bandeau"])) {
        $s = "<WRAP first_footer>{{ %s?nolink&200 }}</WRAP>".PHP_EOL.PHP_EOL;
        $final .= sprintf($s, $this->rjs["bandeau"]);
      }
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
 * (c) Eric Maeker, MD, France, https://github.com/EricMaeker/xmind-dokuwiki-markdown-exporter
 */
$xmd = new XMindToMD();
$xmd->runExport();
$xmd->createPubmedRefNotes();
$xmd->saveOutput();
exit(0);

?>
