<?php

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
    private $output = "";
    private $options = NULL;
    private $version = 1640294872; // echo $(date +%s)  

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
      $withoutLf = str_replace("\n", " / ", $node->title);
      // Convert LF to dokuwiki/markdown style
      $withLf = str_replace("\n", $this->styleLf, $node->title);
      $i = $level - $this->nheaders + 1;
      if ($i < 0)
        $i = 1;
      $indent = str_repeat("  ", $i)."* ";
      switch ($level) {
        case 0:
          if ($this->nheaders > 0) {
            $this->output .= PHP_EOL.PHP_EOL;
            $this->output .= $this->beforeH1;
            $this->output .= $withoutLf;
            $this->output .= $this->afterH1;
            $this->output .= PHP_EOL.PHP_EOL;
            break;
          }
        case 1:
          if ($this->nheaders > 1) {
            $this->output .= PHP_EOL.PHP_EOL;
            $this->output .= $this->beforeH2;
            $this->output .= $withoutLf;
            $this->output .= $this->afterH2;
            $this->output .= PHP_EOL.PHP_EOL;
            break;
          }
        case 2:
          if ($this->nheaders > 2) {
            $this->output .= PHP_EOL.PHP_EOL;
            $this->output .= $this->beforeH3;
            $this->output .= str_replace("\n", " / ", $node->title);
            $this->output .= $this->afterH3;
            $this->output .= PHP_EOL.PHP_EOL;
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
          break;
      }
    }
    
    /**
     * Extra code for pubmed2020 plugin and refnotes
     */
    public function createPubmedRefNotes() {
        $pattern = "/\[\(P(\d+)\)\]/";
        if (preg_match_all($pattern, $this->output, $matches, PREG_PATTERN_ORDER)) {
          echo "* Preparing REFNOTES with PubMed PMID: ".count($matches[1])." reference(s) found";
          $this->output .= PHP_EOL.PHP_EOL.PHP_EOL."===== Références =====".PHP_EOL.PHP_EOL.PHP_EOL;
          $pmids = array_unique($matches[1]);
          sort($pmids, SORT_NUMERIC);
          foreach($pmids as $k => $pmid) {
            $this->output .= "[(P".$pmid.">{{pmid>".$pmid."}})]".PHP_EOL;
          }
          $this->output .= PHP_EOL.PHP_EOL."~~REFNOTES~~".PHP_EOL.PHP_EOL;
        } else {
          //echo "NNNOOOOO".PHP_EOL;
        }
    }
    
    /**
     * Save generated Dokuwiki ou Markdown output
     */
    public function saveOutput() {
        // Write output
        $outputFn = "";
        // Output file?
        if (array_key_exists("o", $this->options) &&
            !empty($this->options["o"])) {
          $outputFn = $this->options["o"];
        } else {
          $outputFn = "./".$fn.$this->outputExt;
        }

        file_put_contents($outputFn, $this->output);
        echo "* Writing output to: ".$outputFn.PHP_EOL;
        echo "* Done".PHP_EOL;
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

