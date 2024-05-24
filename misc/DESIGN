Design notes:

Game Designer: Jamey Stegmaier
Developed by: started apollo1001, cont Victoria_La

If you see this file you probably are unlike person who want to fix this game. I have to warn you - its a mess.
Here is my design notes (I am Victoria_La):

Db tables:

card and structure - are standard "deck" type table with extra data field card_location_arg2
benefit - it is an action stack 
map - is big map data
capital - capital mat map data 
playerextra - extended player table, I copied player table and added some field. 
       In case we support Automa and Shadow Empire bots (because we cannot put bots in real player table)


Card
 - table contains tech cards, tapestry cards, civ cards, tiles and space tiles
   `card_id`  - unique card id auto-inc
   `card_type` - card type, defined by contants such as CARD_CIVILIZATION

 Civilizations in `card` table
   `card_type` - 5 (CARD_CIVILIZATION)
   `card_type_arg` - unique civ type defined in material file
   `card_location` - `deck_civ` - when in deck; `hand` when in hand; `discard` when in discard
   `card_location_arg` - player_id when location is `hand`; deck position when `deck_civ` or `discard`
   `card_location_arg2` - not used for civ

 Tapestries in `card` table
   `card_type` - 3 (CARD_TAPESTRY)
   `card_type_arg` - unique tapestry card type defined in material file, there are multiple instances of TRAP card
   `card_location` - `deck_tapestry` - when in deck; `hand` when in hand; `discard` when in discard; `era#` where # is 1,2,3,4,5,6
   `card_location_arg` - player_id when location is `hand` or `era*`; deck position when `deck_tapestry` or `discard`   
   `card_location_arg2` - ? use for special effects
 Hex Tiles in `card` table
   `card_type` - 1 (CARD_TERRITORY)
   `card_type_arg` - unique territory type defined in material file
   `card_location` - `deck_territory` - when in deck; `hand` when in hand; `discard` when in discard; `map` on main map; `islanders` - when on islanders civ map
   `card_location_arg` - player_id when location is `hand`; deck position when `deck_tapestry` or `discard`; rotation (0-5) when `map`   
   `card_location_arg2` - when location is `map` used for coords in form of ${x}_${y}, i.e. '1_2'

Structures
 - table contains outposts, cubes, landmarks, income buildings
   `card_id`  - unique card id auto-inc
   `card_type` - card type, defined by contants such as BUILDING_OUTPOST
   `card_location_arg` - player_id
   `card_type_arg` - 1 when on map (toppled)
  Income Buildings
   `card_location` - `capital_cell_2300663_11_9` when in capital `capital_cell_${player_id}_${x}_${y}`; `income` - when in income track; `land_${x}_${y}` when on map
  Landmark
   `card_type` - 6 (BUILDING_LANDMARK)
   `card_location` - `landmark_mat_slot3` when on mat (`landmark_mat_slot${lm_type}`), `hand` - when in hand (usually only automa)
   `card_location_arg2` - landmark type id
  Cube
   `card_location` - `tech_slot_1_2` ( `tech_slot_${track}_${slot}`) - when on track; `civ_15_2` (`civ_${civ_type}_${slot}`) - when on civ
              
Benefit
   `benefit_id` - primary key
   `benefit_category` - there are few categories for some reason which handled differently: standard, bonus, civ
   `benefit_type` - type of benefit (i.e. subrule), in case of bonus its type of resource to pay (???)
   `benefit_prerequisite` - not actually prerequisite, its an order field, lower number will go first
   `benefit_quantity` - quantify for standard, number of pay resources for bonus type
   `benefit_data` - assorted crap, I use it to put "reason" for standard
   `benefit_player_id` - who own it


benefit_category:
standard - benefit_type in this case number representing standard benefit listen in $this->benefit_types table
           benefit_quantity - how many times
o,...    - when starting with o (mean or), rest is list of standard benefit player can choose as action (one of)
a,...    - when starting with a (means and), rest if list of standard benefit all of them must be taken, but player choose order

civ      - this is almost stanard benefit but benefit_type is civilization id, not the key in benefit_types
bonus    - this is weird one
           benefit_quantity - how many resources/things to pay
           benefit_type - what to pay, I think its key into   $this->benefit_types but used in reverse meaning
           benefit_data - list sep by , or stanard benefit to gain
           
   
The benefit resolution rules:

Lets call everything that can give you something a "benefit" that include standard benefits, bonuses, awards, civ ability, tech updates,tapestry abilities, 
etc

A benefit may contain more than one simple actions 
  A, B - A then B
  A / B - A or B (can be more)
  A + B - A then B or B then A
  A > B - A then B (where A has to be payed in full)
  
 Simple action can be:
 - mandatory if possible - can decline if not possible
 - mandatory - cannot be performed if not possible
 - mandatory payment - payment has to be in full (same as mandatory)
 - mandatory gain - gain as much as possible, if not cut off max (same as mandatory if possible)
 - optional - provide decline button
 
 Track advancement slot:
 - Standard benefit - mandatory if possible, if multiple simple action threated as A + B
 - Bonus - optional A > B (A is mandatory payment, B is mandatory is possible)
 - Landmark bonus - only if advanced (i.e from below) on slot (except Futurists) and landmark is not claimed
 
 Tech card:
 - Circle - optional
 - Square - mandatory if possible, prereq
 
 Income:
 - Civ abilities - optional, multiple threated as A + B
 - Play Tapestry - mandatory is possible, if not face down card
 - Upgrade - optional
 - Income - mandatory gain
 
 
 TrackMove(track,spot, direction,freedom,modes)
 direction - 0/+1/-1
 freedome - optional, mandatory, mandatory if possible
 modes - take_benefit, pay_bonus, free_bonus, maxout_bonus, no_landmark
 
 

