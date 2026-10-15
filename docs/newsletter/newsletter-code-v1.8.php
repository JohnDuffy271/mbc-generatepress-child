<?php

    //  For reference the code below is a PHP Snippet named "newsletter-code"
    //  For the new website
    //  John Duffy - September 2021 - amended November 2025
    //  Version 1.8

?>

    <style type="text/css">

        #contentTable{
            border:2px none red;
            //border-style: solid;
            border-style: none;
            margin-top:1px;
              width:650px;
            padding:1px;
            border-spacing:0px;
            background-color:white;
        }

        .articleTable{
            border:2px none green;
            //border-style: solid;
            border-style: none;
            margin-top:0px;
            margin-left:0px;
            margin-right:0px;
            margin-bottom:0px;
            width:650px;
            padding:0px;
            background-color:white;
        }

        .articleTable td{
            border:2px none blue;
            //border-style: solid;
            border-style: none;
            padding-left:2px;
            padding-right:2px;
            padding-top:2px;
            padding-bottom:1px;
            background-color:white;
        }
        .articleTable th{
            align="left";
            text-align:left; //new
            width="100%";
            //border:2px none black;
            //border-style: solid;
            border-style: none;
            padding:2px;
            line-height:100%;
            background-color:white;
        }

        .headerTop{
            background-color:#ffff66;
            border-top:0px none black;
            border-bottom:0px none red;
            //border-style: solid;
            text-align:center;
            padding:0px;
        }
        .adminText{
            font-size:10px;
            color:blue;
            line-height:200%;
            font-family:Arial, Helvetica, "sans-serif";
            font-weight: normal;
        }

        .excerptText{
            font-size:20px;
            padding-left:8px;
            color:black;
            line-height:100%;
            font-family:Arial, Helvetica, "sans-serif";
        }

        .headerBar{
            background-color:white;
            border-top:0px none pink;
            border-bottom:0px none black;
            //border-style: solid;
            padding:0px;
        }
        .headerBarText{
            color:black;
            font-size:30px;
            font-family:Arial, Helvetica, "sans-serif";
            font-weight:normal;
            text-align:left;
        }
        .title{
            font-size:30px;
            font-weight:bold;
            color:black;
            font-family:Arial, Helvetica, "sans-serif";
            line-height:100%;
            text-align:left;
            align:left;
        }


        .titleb{
            font-size:26px;
            font-weight:bold;
            color:black;
            font-family:Arial, Helvetica, "sans-serif";
            line-height:100%;
            text-align:left;
            align:left;
        }


        .mainTitle{
            font-size:50px;
            font-weight:bold;
            color:black;
            font-family:Arial, Helvetica, "sans-serif";
            line-height:100%;
            padding:0px;
            text-align:left;
        }


        .subTitle{
            font-size:30px;
            font-weight:bold;
            color:blue;
            //font-style:normal;
            font-family:Arial, Helvetica, "sans-serif";
            line-height:150%;
            padding:1px;
            border:2px none red;
            text-align:left;
            border-style: solid;

        }

        .articleTitle{
            font-size:34px;
            font-weight:bold;
            color:green;
            //font-style:normal;
            font-family:Arial, Helvetica, "sans-serif";
            line-height:150%;
            padding:1px;
            border:2px none blue;
            text-align:left;
            border-style: solid;

        }


        .footerRow{
            background-color:#ffff66;
            border-top:0px none green;
            padding:20px;
        }
        .footerText{
            font-size:10px;
            color:navy;
            line-height:100%;
            font-family:Arial, Helvetica, "sans-serif";
        }
        a,a:link,a:visited{
            color:blue;
            text-decoration:underline;
            font-weight:normal;
        }
        .headerTop a{
            color:blue;
            font-weight:normal;
        }
        .footerRow a{
            color:blue;
            text-decoration:underline;
            font-weight:normal;
        }

        body{
            //background-color:#e69202;
            background: url( 'https://moortownbaptistchurch.org.uk/wp-content/uploads/OrangeBg-1.jpg') 0 0 repeat;
            background-attachment: fixed;
            // width:650px;
            //text-align:center; /* for IE */
            text-align:left;
            // margin-left:auto;
             // margin-right:auto;
             cellpadding:5px;
             border-spacing:2px;
            border:2px none blue;
            //border-style: solid;
            border-style: none;
        }

        div#wrapper {
            //text-align:right; /* reset text alignment */
            width:650px; /* or a percentage, or whatever */
            margin-top:0px; /* for the rest */
            margin-bottom:0px;
            margin-left:auto;
             margin-right:auto;
            }

        .backgroundTable{
            background-color:white;
            width:650px;
             padding:2px;
             border-spacing:2px;
            border:2px none blue;
            //border-style: solid;
            border-style: none;
        }

        hr{
            padding: 0px;
            padding-top: 3px;
            margin: 0px;
            //border:2px none blue;
            //border-style: solid;
          }
    </style>


    <body align="center">
    <div id="wrapper">
    <table width="600px" border-spacing="0" class="backgroundTable";>
        <tr><td valign="top" align="center">

        <table id="contentTable">
            <tr><td>
                <table width="600px" padding="0" border-spacing="0">
                <tr><td class="headerTop" align="right" style="border-top: 0px none #000000;border-bottom: 0px none #FF0000;text-align: center;padding: 0px;">
                <div class="adminText" >Email not displaying correctly?
                    <a class="adminText" href="*|ARCHIVE|*">View it in your browser.</a>

                </div>
            </td></tr>
            <tr><td class="headerBar">
                <div class="headerBarText">
                    <div style="text-align: left;"><a href="http://moortownbaptistchurch.us2.list-manage.com/track/click?u=f3c5220ecc633cc0f942cfa29&id=e01f2a0b2d&e=b5d18ac046"
                        style="color: #17488a;text-decoration: underline;font-weight: normal;"><img src="https://moortownbaptistchurch.org.uk/wp-content/uploads/MBC_Newsletter_Header_20251223-.jpg"
                        alt="Moortown Baptist Church Newsletter" border="0" style="margin:0; padding:0; max-width: 600px;" width="600" height="338"></a>
                    </div>
                </div>
            </td></tr>
        </table>
        <!-- <span class="mainTitle">Moortown Baptist News</span><br> commented out at John Sherbourne's request-->
        </td></tr>
    </table>


<?php

    $title_text = str_replace("\\","",sanitize_text_field(do_shortcode('[sc name="newsletter-title"]')));
    $subtitle_text = str_replace("\\","",sanitize_text_field(do_shortcode('[sc name="newsletter-sub-title"]')));
    $image_address = do_shortcode('[sc name="newsletter-image-url"]');
    $image_width = do_shortcode('[sc name="newsletter-image-width"]');
    $image_height = do_shortcode('[sc name="newsletter-image-height"]');
    $no_posts = do_shortcode('[sc name="newsletter-no-of-posts"]');
    $missing_image_url = 'https://moortownbaptistchurch.org.uk/wp-content/uploads/NoImage.jpg';
?>

    <table class = "articleTable">
    <tbody>
    <?php echo("<tr><th align='left' colspan='1' width='100%'><span class='title'>$title_text</span></th></tr><tr><td align='LEFT' valign='TOP' width='100%'>"); ?>
    <?php
        if (!empty($image_address)) {
        echo "<img align='left' alt='MBC' src='$image_address'
          style='margin-left: 2px; margin-right: 2px; margin-top: 2px;
                 margin-bottom: 2px; float: left;
                 width: {$image_width}px; height: {$image_height}px;' />";
        }
    ?>


<?php
    //echo("</td></tr><tr><td align='LEFT' valign='TOP' width='100%'><span class='title'>$subtitle_text</span></td></tr></tbody></table><hr />");

 ?>

<?php
    $sticky = get_option( 'sticky_posts' );

    $args = array(
            'post_type'             => 'post',
            'post_status'            => 'publish',
            'posts_per_page'         => 1,
            'post__in'                 => $sticky,
            'ignore_sticky_posts'     => 1,
            'showposts'             => $no_posts,
            'cat'                    => array(5,8,9,10,36,38,42,59,-19,-27),
            'orderby'                => 'date',
            'order'                  => 'DESC'
            );

    $query = new WP_Query( $args );

    if ($sticky)
        $stickyposts = $query->found_posts;
    else
        $stickyposts = 0;

    if ( $stickyposts > 0)
    {
        if ( $query->have_posts() ) :
        while ( $query->have_posts() ) : $query->the_post(); ?>
            <table class = "articleTable">
                <tbody>
                    <tr>
                    <th colspan="2"; align="left"; text-align="left";><span class = "titleb"><?php the_title(); ?></span></th>
                    </tr>
                    <tr>
                        <td width="30%";  valign="top" >
                        <?php
                            $url = wp_get_attachment_url( get_post_thumbnail_id($query->$post->ID), 'medium' );
                            if(empty($url))
                            {
                                $url = $missing_image_url;
                            }
                        ?>
                        <a href="<?php the_permalink(); ?>" target = "_blank"><img src="<?php echo $url ?>"  height= "200" width="200";/></a></td>
                        <td width="70%";  valign="top" >
                                <div class = "excerptText"><?php the_excerpt(); ?>
                                <a href="<?php the_permalink(); ?>" target = "_blank"></a></div>
                        </td>
                     </tr>
                </tbody>
            </table>
            <hr/>
         <?php
         endwhile;
         endif;
    }
?>


 <?php wp_reset_postdata(); ?>


 <?php
    if ($stickyposts > $no_posts )
        $displayposts = $stickyposts;
    else
        $displayposts = $no_posts - $stickyposts;


    if ($displayposts > 0)
    {
        $args2 = array(
            'post_type'             => 'post',
            'post_status'            => 'publish',
            'posts_per_page'         => 1,
            'post__not_in'             => $sticky,
            'ignore_sticky_posts'     => 1,
            'showposts'             => $displayposts,
            'cat'                    => array(5,8,9,10,36,38,42,59,-19,-27),
            'orderby'                => 'date',
            'order'                  => 'DESC'
        );

        $query2 = new WP_Query( $args2 );

    if ( $query2->have_posts() ) :
    while ( $query2->have_posts() ) : $query2->the_post(); ?>

        <table class = "articleTable">
            <tbody>
                <tr>
                    <th colspan="2"; align="left"; text-align="left";><span class = "titleb"><?php the_title(); ?></span></th>
                </tr>
                <tr>
                    <td width="30%"; valign="top" >
                    <?php
                        $url = wp_get_attachment_url( get_post_thumbnail_id($query2->$post->ID), 'medium' );
                        if(empty($url))
                        {
                            $url = $missing_image_url;
                        }
                    ?>
                    <a href="<?php the_permalink(); ?>" target = "_blank"><img src="<?php echo $url ?>" height= "200" width="200"/></a></td>
                    <td width="70%"; valign="top" >
                            <div class = "excerptText"><?php the_excerpt(); ?>
                            <a href="<?php the_permalink(); ?>" target = "_blank"></a></div>
                    </td>
                 </tr>
            </tbody>
        </table>
        <hr/>
<?php
        endwhile;
        endif;
        }
?>

<?php wp_reset_postdata(); ?>
<?php wp_reset_query(); ?>
