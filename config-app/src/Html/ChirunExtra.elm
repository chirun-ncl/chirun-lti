module Html.ChirunExtra exposing 
    ( text
    , optional_attribute
    , aria_expanded
    , aria_current
    , role
    )

{- Some functions which provide extra utilities for rendering HTML. -}

import Html as H
import Html.Attributes as HA
import Localise exposing (localise)

{- Localise a string and return a text node. -}
text = localise >> H.text

{- An attribute that is either present or not. -}
optional_attribute : String -> Bool -> List (H.Attribute msg)
optional_attribute name on = if on then [HA.attribute name ""] else []

aria_expanded : Bool -> List (H.Attribute msg)
aria_expanded = optional_attribute "aria-expanded"

aria_current : Bool -> List (H.Attribute msg)
aria_current = optional_attribute "aria-current"

role : String -> H.Attribute msg
role = HA.attribute "role"
